<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\elements\User;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\FileHelper;
use craft\web\UrlManager;
use craft\web\View;
use mutation\translate\base\Controller;
use mutation\translate\helpers\CsvHelper;
use mutation\translate\tests\_support\ImportControllerWithFile;
use mutation\translate\Translate;
use yii\base\Event;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Request;
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
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::ADD_TRANSLATIONS_PERMISSION, Translate::EXPORT_TRANSLATIONS_PERMISSION, Translate::IMPORT_TRANSLATIONS_PERMISSION]);

        foreach ([
            ['translations-admin/messages/get-translations', ['category' => 'notconfigured'], 'GET'],
            ['translations-admin/messages/add', ['category' => 'notconfigured', 'message' => 'x'], 'POST'],
            ['translations-admin/messages/add-multiple', ['category' => 'notconfigured', 'sourceMessages' => [['message' => 'x']]], 'POST'],
            ['translations-admin/export/export', ['category' => 'notconfigured'], 'POST'],
            ['translations-admin/import/import', ['category' => 'notconfigured'], 'POST'],
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

        $this->assertSame(
            ['success' => false, 'error' => 'This message already exists in the category.'],
            $this->runAction('translations-admin/messages/add', ['category' => 'site', 'message' => 'Added'])->data
        );

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
        $this->assertSame(['success' => true, 'deletedIds' => []], $this->runAction('translations-admin/messages/save', ['translations' => ['fr-CA' => [$allowed => 'Permis']]])->data);
        $this->assertSame('Permis', $this->storedTranslation('Allowed', 'fr-CA'));

        $data = $this->runAction('translations-admin/messages/save', ['translations' => ['fr-CA' => [$allowed => 'Encore', $legacy => 'Modifié']]])->data;
        $this->assertSame(['success' => false, 'deletedIds' => []], $data);
        $this->assertSame('Encore', $this->storedTranslation('Allowed', 'fr-CA'));
        $this->assertSame('Ancienne catégorie', $this->storedTranslation('Legacy category', 'fr-CA', 'legacy'));

        // A message deleted in the meantime (e.g. by another user) doesn't make every following save fail, and is reported
        // so the control panel can remove it from the list
        $this->assertSame(
            ['success' => true, 'deletedIds' => [999999]],
            $this->runAction('translations-admin/messages/save', ['translations' => ['fr-CA' => [$allowed => 'Toujours', 999999 => 'Supprimé'], 'en-US' => [999999 => 'Deleted']]])->data
        );
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
        $this->assertSame(['success' => false], $this->runAction('translations-admin/messages/delete', ['sourceMessageId' => [$allowed, $legacy]])->data);
        $this->assertSame([], $this->sourceMessageIds('Allowed'));
        $this->assertSame([$legacy], $this->sourceMessageIds('Legacy category', 'legacy'));

        // Like the save action, a message deleted in the meantime (e.g. by another user) isn't a failure
        $this->assertSame(['success' => true], $this->runAction('translations-admin/messages/delete', ['sourceMessageId' => [$allowed, 999999, 'abc']])->data);

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
        $this->assertSame('No csv file uploaded.', $this->flash('error'));
        $this->assertSame(0, $this->countSourceMessages());
    }

    public function testAddMultipleWhenTheMessagesAreAlreadyInTheCategory(): void
    {
        $this->plugin()->messages->addMessage('Existing', 'other', ['fr-CA' => 'Existant']);
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::ADD_TRANSLATIONS_PERMISSION]);

        $data = $this->runAction('translations-admin/messages/add-multiple', ['category' => 'other', 'sourceMessages' => [
            ['message' => 'Existing', 'languages' => ['fr-CA' => 'Remplacé ?']],
        ]])->data;
        $this->assertSame(['success' => false, 'sourceMessages' => []], $data);
        $this->assertSame('Existant', $this->storedTranslation('Existing', 'fr-CA', 'other'), 'The existing translations are kept');

        // The control panel tells how many were skipped
        $data = $this->runAction('translations-admin/messages/add-multiple', ['category' => 'other', 'sourceMessages' => [
            ['message' => 'Existing', 'languages' => []],
            ['message' => 'New', 'languages' => ['fr-CA' => 'Nouveau']],
        ]])->data;
        $this->assertTrue($data['success']);
        $this->assertSame(['New'], array_column($data['sourceMessages'], 'message'));
    }

    public function testImport(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::IMPORT_TRANSLATIONS_PERMISSION]);
        $file = Craft::$app->getPath()->getTempPath() . '/import-test.csv';
        // The copies of the uploaded files are named after them
        $tempFiles = fn() => glob(Craft::$app->getPath()->getTempPath() . '/translations*');
        $import = function(string $content, string $fileName = 'translations.csv') use ($file) {
            file_put_contents($file, $content);
            $this->plugin()->controllerMap['import'] = ['class' => ImportControllerWithFile::class, 'filePath' => $file, 'fileName' => $fileName];
            return $this->runAction('translations-admin/import/import', ['category' => 'other']);
        };
        $tempFilesBefore = $tempFiles();

        try {
            $response = $import("Key,en-US,fr-CA\nHello,Hello EN,Bonjour\n");
            $this->assertSame('2 translations added. 0 translations updated. 1 key created.', $this->flash('success'));
            $this->assertSame('Bonjour', $this->storedTranslation('Hello', 'fr-CA', 'other'));
            $location = $response->getHeaders()->get('location');
            $this->assertStringContainsString('translations-admin/import-messages', $location);
            $this->assertStringContainsString('category=other', $location, 'The category stays selected');

            $this->assertNull($import("Key,fr-CA\nHello,Allô\n", 'translations.xlsx'));
            $this->assertSame('Only .csv, .tsv and .txt files can be imported.', $this->flash('error'));

            $this->assertNull($import("Key,Notes\nHello,Note\n"));
            $this->assertStringStartsWith('No language column could be found', $this->flash('error'));

            // The key column is missing: the translations would be added as keys
            $this->assertNull($import("fr-CA,en-US\nAllô,Hi\n"));
            $this->assertSame('The first column must be the key, but its header is a language (fr-CA).', $this->flash('error'));
            $this->assertSame([], $this->sourceMessageIds('Allô', 'other'));

            $this->assertSame('Bonjour', $this->storedTranslation('Hello', 'fr-CA', 'other'));
            $this->assertSame($tempFilesBefore, $tempFiles(), 'The uploaded files are deleted');
        } finally {
            unset($this->plugin()->controllerMap['import']);
            @unlink($file);
        }
    }

    public function testUtilities(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::TRANSLATIONS_UTILITIES_PERMISSION]);

        $this->runAction('translations-admin/utilities/missing');
        $this->assertSame('10 translation(s) imported.', $this->flash('success'));

        $this->plugin()->messages->saveMessages(['fr-CA' => [$this->sourceMessageIds('Parse default')[0] => 'Par défaut']]);
        $this->runAction('translations-admin/utilities/export');
        $this->assertSame('20 translation(s) exported to PHP files.', $this->flash('success'));

        $this->plugin()->messages->deleteAllMessages();
        $this->runAction('translations-admin/utilities/import');
        $this->assertSame('1 translation(s) imported to the database.', $this->flash('success'));
        $this->assertSame('Par défaut', $this->storedTranslation('Parse default', 'fr-CA'));

        // A file where the language folder should be
        $this->removeTranslationFiles();
        FileHelper::writeToFile(Craft::$app->getPath()->getSiteTranslationsPath() . '/fr-CA', 'not a folder');
        try {
            $this->runAction('translations-admin/utilities/export');
            $this->assertSame('Translations couldn’t be exported to PHP files.', $this->flash('error'));
        } finally {
            unlink(Craft::$app->getPath()->getSiteTranslationsPath() . '/fr-CA');
        }
    }

    public function testNavigationDependsOnThePermissions(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION]);
        $this->assertSame(['translations'], array_keys($this->plugin()->getCpNavItem()['subnav']));

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::EXPORT_TRANSLATIONS_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);
        $this->assertSame(['translations', 'export', 'settings'], array_keys($this->plugin()->getCpNavItem()['subnav']));

        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::IMPORT_TRANSLATIONS_PERMISSION, Translate::TRANSLATIONS_UTILITIES_PERMISSION]);
        $this->assertSame(['translations', 'import', 'utilities'], array_keys($this->plugin()->getCpNavItem()['subnav']));
    }

    public function testCategoryUrls(): void
    {
        $settings = $this->plugin()->getSettings();
        $categories = $settings->categories;
        $settings->categories = ['site', 'site.forms', 'c++', 'a b', 'Catégorie'];

        try {
            $event = new RegisterUrlRulesEvent();
            Event::trigger(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, $event);
        } finally {
            $settings->categories = $categories;
        }
        $urlManager = new \yii\web\UrlManager(['enablePrettyUrl' => true, 'enableStrictParsing' => true, 'cache' => null, 'rules' => $event->rules]);
        $route = function(string $path) use ($urlManager) {
            $request = new Request();
            // The path is decoded, like the path of a request
            $request->setPathInfo($path);
            return $urlManager->parseRequest($request);
        };

        foreach (['site', 'site.forms', 'c++', 'a b', 'Catégorie'] as $category) {
            $this->assertSame(['translations-admin/messages/index', ['category' => $category]], $route("translations-admin/$category"), $category);
        }
        // The categories aren't regular expressions
        $this->assertFalse($route('translations-admin/site-forms'));
        $this->assertFalse($route('translations-admin/c'));
        $this->assertFalse($route('translations-admin/other'), 'Only the categories of the settings');
        $this->assertSame(['translations-admin/messages/index', []], $route('translations-admin'));
    }

    public function testCategoriesCantBeLongerThanTheColumn(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);

        $this->assertNull($this->runAction('translations-admin/settings/save', [
            'settings' => ['categories' => [['category' => 'site'], ['category' => str_repeat('é', 256)]]],
        ]));
        $this->assertSame(['The categories can’t be longer than 255 characters.'], $this->plugin()->getSettings()->getErrors('categories'));

        $this->plugin()->getSettings()->categories = ['site', str_repeat('é', 255)];
        $this->assertTrue($this->plugin()->getSettings()->validate(['categories']), 'The length is in characters, not bytes');
    }

    public function testSettingsPageShowsTheErrors(): void
    {
        $this->loginAs([Controller::ACCESS_PLUGIN_PERMISSION, Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION]);
        $this->runAction('translations-admin/settings/save', [
            'settings' => ['pluginName' => ' ', 'categories' => [['category' => 'site'], ['category' => 'a/b']]],
        ]);

        // The page rendered after the save failed
        $template = $this->runAction('translations-admin/settings/index', [], 'GET')->getBehavior('template');
        $html = Craft::$app->getView()->renderPageTemplate($template->template, $template->variables, View::TEMPLATE_MODE_CP);

        $this->assertStringContainsString('Plugin Name cannot be blank.', $html);
        $this->assertStringContainsString('The category “a/b” can’t contain slashes.', $html);
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

        $this->assertSame(['success' => false, 'deletedIds' => []], $data);
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

    /**
     * Returns and removes a flash message of the last action.
     */
    private function flash(string $type): ?string
    {
        return Craft::$app->getSession()->getFlash($type, null, true);
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
