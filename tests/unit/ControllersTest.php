<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\elements\User;
use mutation\translate\base\Controller;
use mutation\translate\helpers\CsvHelper;
use mutation\translate\Translate;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Permissions and parameters of the control panel actions.
 */
class ControllersTest extends TestCase
{
    public function testGetTranslationsRequiresThePluginAccess(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site', ['fr-CA' => 'Bonjour']);

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION]);
        $data = $this->runAction('translations-admin/messages/get-translations', ['category' => 'site'], 'GET')->data;
        $this->assertSame(['en-US', 'fr-CA'], array_column($data['languages'], 'id'));
        $this->assertSame('Bonjour', $data['sourceMessages'][0]['languages']['fr-CA']);

        $this->loginAs([]);
        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('translations-admin/messages/get-translations', ['category' => 'site'], 'GET');
    }

    public function testCategoriesAreValidated(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::ADD_TRANSLATIONS_PERMISSION, Translate::EXPORT_TRANSLATIONS_PERMISSION]);

        foreach ([
            ['translations-admin/messages/get-translations', ['category' => 'notconfigured'], 'GET'],
            ['translations-admin/messages/add', ['category' => 'notconfigured', 'message' => 'x'], 'POST'],
            ['translations-admin/export/export', ['category' => 'notconfigured'], 'POST'],
        ] as [$route, $params, $method]) {
            try {
                $this->runAction($route, $params, $method);
                $this->fail("$route accepted an unknown category");
            } catch (BadRequestHttpException) {
            }
        }
        $this->assertSame(0, $this->countSourceMessages());
    }

    public function testAdd(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::ADD_TRANSLATIONS_PERMISSION]);

        $data = $this->runAction('translations-admin/messages/add', ['category' => 'site', 'message' => 'Added'])->data;
        $this->assertTrue($data['success']);
        $this->assertSame(['en-US' => '', 'fr-CA' => ''], $data['sourceMessage']['languages']);

        $this->assertSame(['success' => false], $this->runAction('translations-admin/messages/add', ['category' => 'site', 'message' => 'Added'])->data);

        $data = $this->runAction('translations-admin/messages/add-multiple', ['category' => 'other', 'sourceMessages' => [
            ['message' => 'Pasted', 'languages' => ['fr-CA' => 'Collé']],
            ['languages' => ['fr-CA' => 'No message']],
            ['message' => 'Zero', 'languages' => ['fr-CA' => '0']],
        ]])->data;
        $this->assertTrue($data['success']);
        $this->assertSame(['en-US' => '', 'fr-CA' => 'Collé'], $data['sourceMessages'][0]['languages']);
        $this->assertCount(2, $data['sourceMessages']);
        $this->assertSame('0', $this->storedTranslation('Zero', 'fr-CA', 'other'), '"0" is a translation, not an empty value');

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION]);
        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('translations-admin/messages/add', ['category' => 'site', 'message' => 'Not allowed']);
    }

    public function testAddRejectsInvalidValues(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::ADD_TRANSLATIONS_PERMISSION]);

        foreach ([
            ['translations-admin/messages/add', ['category' => 'site', 'message' => ['x']]],
            ['translations-admin/messages/add-multiple', ['category' => 'site', 'sourceMessages' => 'x']],
        ] as [$route, $params]) {
            try {
                $this->runAction($route, $params);
                $this->fail("$route accepted an invalid value");
            } catch (BadRequestHttpException) {
            }
        }

        $data = $this->runAction('translations-admin/messages/add-multiple', ['category' => 'site', 'sourceMessages' => [
            'x',
            ['message' => ['x']],
            ['message' => 'Valid', 'languages' => ['fr-CA' => ['x'], 'en-US' => '  ']],
        ]])->data;
        $this->assertCount(1, $data['sourceMessages']);
        $this->assertNull($this->storedTranslation('Valid', 'fr-CA'));
        $this->assertNull($this->storedTranslation('Valid', 'en-US'), 'Blank translations are stored as empty');
    }

    public function testSaveOnlyChangesMessagesOfTheConfiguredCategories(): void
    {
        $allowed = $this->plugin()->messages->addMessage('Allowed', 'site')->id;
        $legacy = $this->insertRaw('legacy', 'Legacy category', ['fr-CA' => 'Ancienne catégorie']);

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::SAVE_TRANSLATIONS_PERMISSION]);
        $this->assertSame(['success' => true], $this->runAction('translations-admin/messages/save', ['translations' => ['fr-CA' => [$allowed => 'Permis']]])->data);
        $this->assertSame('Permis', $this->storedTranslation('Allowed', 'fr-CA'));

        $data = $this->runAction('translations-admin/messages/save', ['translations' => ['fr-CA' => [$allowed => 'Encore', $legacy => 'Modifié']]])->data;
        $this->assertSame(['success' => false], $data);
        $this->assertSame('Encore', $this->storedTranslation('Allowed', 'fr-CA'));
        $this->assertSame('Ancienne catégorie', $this->storedTranslation('Legacy category', 'fr-CA', 'legacy'));

        // A message deleted in the meantime (e.g. by another user) doesn't make every following save fail
        $this->assertSame(['success' => true], $this->runAction('translations-admin/messages/save', ['translations' => ['fr-CA' => [$allowed => 'Toujours', 999999 => 'Supprimé']]])->data);
        $this->assertSame('Toujours', $this->storedTranslation('Allowed', 'fr-CA'));

        $this->loginAs([Translate::SAVE_TRANSLATIONS_PERMISSION]);
        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('translations-admin/messages/save', ['translations' => ['fr-CA' => [$allowed => 'Sans accès']]]);
    }

    public function testDeleteOnlyRemovesMessagesOfTheConfiguredCategories(): void
    {
        $allowed = $this->plugin()->messages->addMessage('Allowed', 'site')->id;
        $legacy = $this->insertRaw('legacy', 'Legacy category');

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::DELETE_TRANSLATIONS_PERMISSION]);
        $this->assertSame(['success' => true], $this->runAction('translations-admin/messages/delete', ['sourceMessageId' => [$allowed, $legacy, 999999, 'abc']])->data);
        $this->assertSame([], $this->sourceMessageIds('Allowed'));
        $this->assertSame([$legacy], $this->sourceMessageIds('Legacy category', 'legacy'));

        $this->loginAs([Translate::DELETE_TRANSLATIONS_PERMISSION]);
        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('translations-admin/messages/delete', ['sourceMessageId' => [$legacy]]);
    }

    public function testExport(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site', ['fr-CA' => 'Bonjour']);

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::EXPORT_TRANSLATIONS_PERMISSION]);
        $response = $this->runAction('translations-admin/export/export', ['category' => 'site']);
        $this->assertStringStartsWith(CsvHelper::BOM . "Key,en-US,fr-CA\n", $response->content);
        $this->assertStringContainsString('Hello,,Bonjour', $response->content);
        $this->assertStringContainsString('translations-site.csv', $response->getHeaders()->get('content-disposition'));

        $this->loginAs([]);
        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('translations-admin/export/export', ['category' => 'site']);
    }

    public function testImportWithoutAFile(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::IMPORT_TRANSLATIONS_PERMISSION]);

        $this->assertNull($this->runAction('translations-admin/import/import', ['category' => 'site']));
        $this->assertSame(0, $this->countSourceMessages());
    }

    public function testSettingsCanOnlyChangeThisPlugin(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);

        $this->runAction('translations-admin/settings/save', [
            'pluginHandle' => 'another-plugin',
            'settings' => ['pluginName' => 'Renamed', 'sourceLanguage' => 'en-US', 'categories' => [['category' => 'site']]],
        ]);

        $this->assertSame('Renamed', Craft::$app->getProjectConfig()->get('plugins.translations-admin.settings.pluginName'));
        $this->assertNull(Craft::$app->getProjectConfig()->get('plugins.another-plugin'));
    }

    public function testSourceLanguageCanBeThePrimarySiteLanguage(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);

        $this->runAction('translations-admin/settings/save', [
            'settings' => ['pluginName' => 'Translations', 'sourceLanguage' => '', 'categories' => [['category' => 'site']]],
        ]);

        $this->assertSame('', Craft::$app->getProjectConfig()->get('plugins.translations-admin.settings.sourceLanguage'));
        $this->assertSame(Craft::$app->getSites()->getPrimarySite()->language, $this->plugin()->getSettings()->getSourceLanguage());

        $this->expectException(BadRequestHttpException::class);
        $this->runAction('translations-admin/settings/save', ['settings' => 'x']);
    }

    public function testPluginNameIsRequired(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);
        $storedName = Craft::$app->getProjectConfig()->get('plugins.translations-admin.settings.pluginName');

        $this->assertNull($this->runAction('translations-admin/settings/save', [
            'settings' => ['pluginName' => ' ', 'categories' => [['category' => 'site']]],
        ]));
        $this->assertTrue($this->plugin()->getSettings()->hasErrors('pluginName'));
        $this->assertSame($storedName, Craft::$app->getProjectConfig()->get('plugins.translations-admin.settings.pluginName'), 'Nothing is saved');
    }

    public function testSettingsAreReadOnlyWithoutAdminChanges(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);
        Craft::$app->getConfig()->getGeneral()->allowAdminChanges = false;

        try {
            $this->expectException(ForbiddenHttpException::class);
            $this->runAction('translations-admin/settings/save', ['settings' => ['pluginName' => 'Renamed']]);
        } finally {
            Craft::$app->getConfig()->getGeneral()->allowAdminChanges = true;
        }
    }

    public function testDeleteAllRequiresTheUtilitiesPermission(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site');

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::TRANSLATIONS_UTILITIES_PERMISSION]);
        $this->runAction('translations-admin/utilities/delete');
        $this->assertSame(0, $this->countSourceMessages());

        $this->plugin()->messages->addMessage('Hello', 'site');
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::DELETE_TRANSLATIONS_PERMISSION]);
        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('translations-admin/utilities/delete');
    }

    public function testEveryActionRequiresThePluginAccess(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site', ['fr-CA' => 'Bonjour']);

        // Craft only checks the plugin access of the control panel pages, not of the `actions/...` requests
        foreach ([
            [Translate::EXPORT_TRANSLATIONS_PERMISSION, 'translations-admin/export/export', ['category' => 'site']],
            [Translate::IMPORT_TRANSLATIONS_PERMISSION, 'translations-admin/import/import', ['category' => 'site']],
            [Translate::TRANSLATIONS_UTILITIES_PERMISSION, 'translations-admin/utilities/delete', []],
            [Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION, 'translations-admin/settings/save', ['settings' => ['pluginName' => 'Renamed']]],
        ] as [$permission, $route, $params]) {
            $this->loginAs([$permission]);
            try {
                $this->runAction($route, $params);
                $this->fail("$route doesn't require the plugin access");
            } catch (ForbiddenHttpException) {
            }
        }

        $this->assertSame(1, $this->countSourceMessages());
        $this->assertSame('Translations', $this->plugin()->getSettings()->pluginName);
    }

    public function testIndexRejectsAnUnknownCategory(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION]);
        Craft::$app->getView()->setTemplateMode('cp');

        // The category comes from the URL, as an action param
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = Craft::$app->runAction('translations-admin/messages/index', ['category' => 'other']);
        $this->assertSame('other', $response->getBehavior('template')->variables['category']);

        $this->expectException(NotFoundHttpException::class);
        Craft::$app->runAction('translations-admin/messages/index', ['category' => 'notconfigured']);
    }

    public function testSaveRejectsInvalidTranslations(): void
    {
        $id = $this->plugin()->messages->addMessage('Hello', 'site')->id;

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::SAVE_TRANSLATIONS_PERMISSION]);
        $data = $this->runAction('translations-admin/messages/save', ['translations' => [
            'fr-CA' => [$id => ['x']],
            'en-US' => [$id => 'Hello!'],
        ]])->data;

        $this->assertSame(['success' => false], $data);
        $this->assertNull($this->storedTranslation('Hello', 'fr-CA'));
        $this->assertSame('Hello!', $this->storedTranslation('Hello', 'en-US'), 'The valid translations are still saved');
    }

    public function testCategoriesAreNormalized(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);

        // Blank and duplicate categories are ignored, and the spaces around them are removed
        $this->runAction('translations-admin/settings/save', [
            'settings' => ['categories' => [['category' => 'site'], ['category' => '  '], ['category' => ' other '], ['category' => 'site ']]],
        ]);
        $this->assertSame(['site', 'other'], Craft::$app->getProjectConfig()->get('plugins.translations-admin.settings.categories'));
        $this->assertSame(['site', 'other'], $this->plugin()->getSettings()->getCategories());

        $this->assertNull($this->runAction('translations-admin/settings/save', [
            'settings' => ['categories' => [['category' => '']]],
        ]), 'At least one category is required');
        $this->assertTrue($this->plugin()->getSettings()->hasErrors('categories'));

        $this->plugin()->getSettings()->categories = [['category' => ''], ['category' => 'other'], ['category' => ' other ']];
        $this->assertSame(['other'], $this->plugin()->getSettings()->getCategories(), 'Also when not validated (e.g. set in the config file)');
    }

    public function testCategoriesCantBePaths(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);

        // The PHP translation files are named after the categories
        foreach (['../../config/general', 'sub\\dir', '..'] as $category) {
            $this->assertNull($this->runAction('translations-admin/settings/save', [
                'settings' => ['categories' => [['category' => 'site'], ['category' => $category]]],
            ]), $category);
            $this->assertTrue($this->plugin()->getSettings()->hasErrors('categories'), $category);
        }
        $this->assertNotContains('../../config/general', (array)Craft::$app->getProjectConfig()->get('plugins.translations-admin.settings.categories'));
    }

    public function testTemplatePathsCantBeSavedFromTheControlPanel(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);

        // A config file setting: it would let the "Parse templates" utility read any folder
        $this->runAction('translations-admin/settings/save', [
            'settings' => ['categories' => [['category' => 'site'], ['category' => 'other']], 'templatePaths' => ['/']],
        ]);

        $this->assertSame([], $this->plugin()->getSettings()->templatePaths);
        $this->assertNull(Craft::$app->getProjectConfig()->get('plugins.translations-admin.settings.templatePaths'));
    }

    private function loginAs(array $permissions): void
    {
        // The plugin access is nested under the control panel access, it is ignored without it
        if (in_array(Controller::ACCESS_PLUGIN_PERMISSION, $permissions, true)) {
            $permissions[] = 'accessCp';
        }

        $username = 'user-' . md5(implode(',', $permissions));
        $user = User::find()->username($username)->status(null)->one();
        if (!$user) {
            $user = new User(['username' => $username, 'email' => "{$username}@example.com"]);
            $this->assertTrue(Craft::$app->getElements()->saveElement($user, false));
            Craft::$app->getUserPermissions()->saveUserPermissions($user->id, $permissions);
        }

        Craft::$app->getUser()->setIdentity($user);
    }

    private function runAction(string $route, array $params = [], string $method = 'POST'): ?Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $request = Craft::$app->getRequest();
        $request->enableCsrfValidation = false;
        $request->setBodyParams($method === 'POST' ? $params : []);
        $request->setQueryParams($method === 'GET' ? $params : []);

        $response = Craft::$app->runAction($route);

        return $response instanceof Response ? $response : null;
    }
}
