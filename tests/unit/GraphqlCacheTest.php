<?php

namespace mutation\translate\tests\unit;

use Craft;
use mutation\translate\models\Settings;
use mutation\translate\resolvers\StaticMessageResolver;
use yii\caching\TagDependency;

/**
 * The cached GraphQL results cleared when the translations change (the `gqlCacheInvalidation` setting).
 */
class GraphqlCacheTest extends TestCase
{
    private string $invalidation;

    protected function _before(): void
    {
        parent::_before();
        $this->invalidation = $this->plugin()->settings->gqlCacheInvalidation;
    }

    protected function _after(): void
    {
        $this->plugin()->settings->gqlCacheInvalidation = $this->invalidation;
        parent::_after();
    }

    public function testStaticMessagesResultsAreTagged(): void
    {
        $elements = Craft::$app->getElements();
        $elements->startCollectingCacheInfo();
        StaticMessageResolver::resolve(null, []);
        [$dependency] = $elements->stopCollectingCacheInfo();

        $this->assertInstanceOf(TagDependency::class, $dependency);
        $this->assertContains(StaticMessageResolver::CACHE_TAG, $dependency->tags);
    }

    public function testEveryResultIsClearedByDefault(): void
    {
        $this->assertSame(Settings::GQL_CACHE_INVALIDATION_ALL, (new Settings())->gqlCacheInvalidation);

        $this->cacheResults();
        $this->plugin()->messages->addMessage('Cache all', 'site');

        $this->assertNull(Craft::$app->getGql()->getCachedResult('translations'));
        $this->assertNull(Craft::$app->getGql()->getCachedResult('entries'), 'Like the previous versions');
    }

    public function testOnlyTheStaticMessagesResultsCanBeCleared(): void
    {
        $this->plugin()->settings->gqlCacheInvalidation = Settings::GQL_CACHE_INVALIDATION_STATIC_MESSAGES;
        $gql = Craft::$app->getGql();

        // Every change of the translations clears them
        $changes = [
            'add' => fn() => $this->plugin()->messages->addMessage('Cache tag', 'site'),
            'save' => fn() => $this->plugin()->messages->saveTranslations(['fr-CA' => [$this->sourceMessageIds('Cache tag')[0] => 'Étiquette']]),
            'import' => fn() => $this->plugin()->messages->importMessages('site', [['message' => 'Cache tag', 'languages' => ['fr-CA' => 'Importé']]]),
            'delete' => fn() => $this->plugin()->messages->deleteMessages($this->sourceMessageIds('Cache tag')),
        ];
        foreach ($changes as $change => $callback) {
            $this->cacheResults();
            $callback();

            $this->assertNull($gql->getCachedResult('translations'), $change);
            $this->assertSame(['data' => 'entries'], $gql->getCachedResult('entries'), "$change: the other results stay cached");
        }
    }

    public function testCachedQueriesWithCraft(): void
    {
        $this->plugin()->settings->gqlCacheInvalidation = Settings::GQL_CACHE_INVALIDATION_STATIC_MESSAGES;
        $this->plugin()->messages->addMessage('Cached', 'site', ['fr-CA' => 'Un']);
        $gql = Craft::$app->getGql();
        $query = fn() => $gql->executeQuery($gql->getPublicSchema(), '{ staticMessages(language: ["fr-CA"], category: ["site"]) { key message } }')['data']['staticMessages'][0]['message'] ?? null;

        $this->assertSame('Un', $query());

        // Changed without the plugin: the cached result is still returned
        Craft::$app->getDb()->createCommand()->update('{{%message}}', ['translation' => 'Deux'], ['id' => $this->sourceMessageIds('Cached')[0], 'language' => 'fr-CA'])->execute();
        if ($query() !== 'Un') {
            $this->markTestSkipped('The GraphQL results are not cached in this environment.');
        }

        // Saved with the plugin: the tagged result is cleared
        $this->plugin()->messages->saveTranslations(['fr-CA' => [$this->sourceMessageIds('Cached')[0] => 'Trois']]);
        $this->assertSame('Trois', $query());
    }

    public function testOnlyTheKnownValuesAreValid(): void
    {
        $settings = new Settings(['gqlCacheInvalidation' => 'none']);

        $this->assertFalse($settings->validate(['gqlCacheInvalidation']));
        $settings->gqlCacheInvalidation = Settings::GQL_CACHE_INVALIDATION_STATIC_MESSAGES;
        $this->assertTrue($settings->validate(['gqlCacheInvalidation']));
    }

    /**
     * Caches a result with a `staticMessages` query, tagged like Craft does with the tags collected during the query, and
     * a result without one.
     */
    private function cacheResults(): void
    {
        $gql = Craft::$app->getGql();
        $gql->setCachedResult('translations', ['data' => 'translations'], new TagDependency(['tags' => [StaticMessageResolver::CACHE_TAG]]));
        $gql->setCachedResult('entries', ['data' => 'entries']);

        $this->assertNotNull($gql->getCachedResult('translations'), 'The cache works');
        $this->assertNotNull($gql->getCachedResult('entries'));
    }
}
