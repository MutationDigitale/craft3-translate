<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\db\Query;
use mutation\translate\models\SourceMessage;
use mutation\translate\resolvers\StaticMessageResolver;

class SourceMessageServiceTest extends TestCase
{
    public function testLookupsAreExactMatches(): void
    {
        $service = $this->plugin()->sourceMessage;
        foreach (['Hello', 'hello', 'Café', 'Cafe', 'Pad', 'Pad ', '123'] as $message) {
            $this->assertNotNull($service->createSourceMessage('site', $message), $message);
        }

        $ids = [];
        foreach (['Hello', 'hello', 'Café', 'Cafe', 'Pad', 'Pad ', '123'] as $message) {
            $ids[$message] = $service->getSourceMessageId('site', $message);
            $this->assertNotNull($ids[$message], $message);
        }
        $this->assertCount(7, array_unique($ids), 'Case, accent and trailing space variants are different messages');
        $this->assertNull($service->getSourceMessageId('site', 'Unknown'));
        $this->assertNull($service->getSourceMessageId('other', 'Hello'));

        $byHash = $service->getSourceMessageIdsByCategory('site');
        $this->assertSame($ids['123'], $byHash[SourceMessage::hash('123')]);
        $this->assertCount(7, $byHash);
    }

    public function testCreateSourceMessage(): void
    {
        $service = $this->plugin()->sourceMessage;

        $sourceMessage = $service->createSourceMessage('site', 'Emoji 🎉', ['fr-CA' => 'Émoji 🎉', 'xx-XX' => 'Unknown language']);
        $this->assertNotNull($sourceMessage);
        $this->assertNull($this->storedTranslation('Emoji 🎉', 'en-US'), 'Every site language gets a row');
        $this->assertSame('Émoji 🎉', $this->storedTranslation('Emoji 🎉', 'fr-CA'));
        $this->assertFalse($this->storedTranslation('Emoji 🎉', 'xx-XX'), 'Unknown languages are ignored');

        $this->assertNull($service->createSourceMessage('site', 'Emoji 🎉'), 'Duplicates are rejected');
        $this->assertNotNull($service->createSourceMessage('other', 'Emoji 🎉'), 'The same message is allowed in another category');
        $this->assertNull($service->createSourceMessage('site', ''), 'Empty messages are rejected');
        $this->assertCount(1, $this->sourceMessageIds('Emoji 🎉'));

        $sourceMessage = $service->createSourceMessage('site', 'Blank', ['fr-CA' => '   ', 'en-US' => '0']);
        $this->assertNull($this->storedTranslation('Blank', 'fr-CA'), 'Blank translations are stored as null');
        $this->assertSame('0', $this->storedTranslation('Blank', 'en-US'), '"0" is a translation');
        $this->assertNotNull($sourceMessage->dateCreated);
        $this->assertSame(['en-US' => '0', 'fr-CA' => null], $this->keySorted($sourceMessage->getTranslations()));
    }

    public function testDeletingASourceMessageDeletesItsTranslations(): void
    {
        $sourceMessage = $this->plugin()->sourceMessage->createSourceMessage('site', 'Deleted', ['fr-CA' => 'Supprimé']);
        $this->assertSame('Supprimé', $sourceMessage->getTranslation('fr-CA'));

        $this->assertSame(1, SourceMessage::findOne($sourceMessage->id)->delete());
        $this->assertSame(0, (int)(new Query())->from('{{%message}}')->where(['id' => $sourceMessage->id])->count());
    }

    private function keySorted(array $values): array
    {
        ksort($values);
        return $values;
    }

    public function testCreateDuplicateInsideATransaction(): void
    {
        $service = $this->plugin()->sourceMessage;
        $service->createSourceMessage('site', 'In transaction');

        // The failed insert must only roll back to its savepoint (PostgreSQL aborts the whole transaction otherwise)
        $transaction = Craft::$app->getDb()->beginTransaction();
        $this->assertNull($service->createSourceMessage('site', 'In transaction'));
        $this->assertNotNull($service->createSourceMessage('site', 'Still works'));
        $transaction->commit();

        $this->assertCount(1, $this->sourceMessageIds('In transaction'));
        $this->assertCount(1, $this->sourceMessageIds('Still works'));
    }

    public function testGetOrCreateSourceMessageId(): void
    {
        $service = $this->plugin()->sourceMessage;

        $id = $service->getOrCreateSourceMessageId('site', 'Get or create', $created);
        $this->assertNotNull($id);
        $this->assertTrue($created);

        $this->assertSame($id, $service->getOrCreateSourceMessageId('site', 'Get or create', $created));
        $this->assertFalse($created);
    }

    public function testSourceMessagesArrayForTheControlPanel(): void
    {
        $this->plugin()->messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        $this->insertRaw('site', 'Without translation rows');
        $this->insertRaw('site', 'Old language only', ['de' => 'Alt']);
        $this->plugin()->messages->addMessage('Other category', 'other');

        $messages = array_column($this->plugin()->sourceMessage->getSourceMessagesArrayByCategory('site'), null, 'message');

        $this->assertSame(['Old language only', 'Translated', 'Without translation rows'], $this->sorted(array_keys($messages)));
        $this->assertSame(['en-US' => null, 'fr-CA' => 'Traduit'], $messages['Translated']['languages']);
        $this->assertSame(['en-US' => '', 'fr-CA' => ''], $messages['Without translation rows']['languages']);
        $this->assertSame(['en-US' => '', 'fr-CA' => ''], $messages['Old language only']['languages'], 'Languages without a site are not listed');
        $this->assertIsInt($messages['Translated']['id']);
    }

    public function testAllSourceMessagesForThePhpFiles(): void
    {
        $this->plugin()->messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        $this->insertRaw('site', 'Without translation rows');

        $all = $this->plugin()->sourceMessage->getAllSourceMessages();

        $this->assertSame('Traduit', $all['fr-CA']['site']['Translated']);
        $this->assertSame('', $all['en-US']['site']['Translated']);
        $this->assertSame('', $all['fr-CA']['site']['Without translation rows']);
    }

    public function testGraphqlFallsBackToTheKey(): void
    {
        $this->plugin()->messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        $this->plugin()->messages->addMessage('Other category', 'other', ['fr-CA' => 'Autre']);

        $rows = StaticMessageResolver::resolve(null, ['language' => ['fr-CA', 'en-US'], 'category' => ['site']]);
        $messages = [];
        foreach ($rows as $row) {
            $messages[$row['language']][$row['key']] = $row['message'];
        }

        ksort($messages);
        $this->assertSame(['en-US' => ['Translated' => 'Translated'], 'fr-CA' => ['Translated' => 'Traduit']], $messages);
    }

    public function testGraphqlOnlyReturnsTheConfiguredCategories(): void
    {
        $this->plugin()->messages->addMessage('Configured', 'other');
        $this->insertRaw('notconfigured', 'Removed from the settings', ['en-US' => 'Hidden']);

        $rows = StaticMessageResolver::resolve(null, ['language' => ['en-US'], 'category' => ['other', 'notconfigured']]);
        $this->assertSame(['Configured'], array_column($rows, 'key'));
    }

    public function testGraphqlTranslatesLikeTheSite(): void
    {
        $this->insertRaw('site', 'Base language', ['fr' => 'Langue de base']);
        $this->insertRaw('site', 'Empty row', ['fr-CA' => null, 'fr' => 'Pas utilisé']);
        $this->insertRaw('site', 'Empty string', ['fr-CA' => '']);
        $this->insertRaw('site', 'Without translation rows');

        $messages = [];
        foreach (StaticMessageResolver::resolve(null, ['language' => ['fr-CA'], 'category' => ['site']]) as $row) {
            $messages[$row['key']] = $row['message'];
            $this->assertSame(Craft::t('site', $row['key'], [], 'fr-CA'), $row['message'], $row['key']);
        }

        $this->assertSame([
            'Base language' => 'Langue de base',
            'Empty row' => 'Empty row',
            'Empty string' => 'Empty string',
            'Without translation rows' => 'Without translation rows',
        ], $messages);
    }

    /**
     * Craft translates the messages of the categories other than `site` that aren't translated in the source language.
     */
    public function testGraphqlTranslatesTheOtherCategoriesLikeTheSite(): void
    {
        $this->insertRaw('other', 'Source language only', ['en-US' => 'In the source language', 'fr-CA' => null]);
        $this->insertRaw('other', 'Translated', ['en-US' => 'In the source language', 'fr-CA' => 'Traduit']);
        $this->insertRaw('other', 'Base language', ['en-US' => 'Source', 'fr' => 'Langue de base']);
        $this->insertRaw('other', 'Source base language', ['en' => 'From the en row']);
        $this->insertRaw('other', 'Not translated');

        $messages = [];
        foreach (StaticMessageResolver::resolve(null, ['language' => ['fr-CA', 'en-US'], 'category' => ['other']]) as $row) {
            $messages[$row['language']][$row['key']] = $row['message'];
            $this->assertSame(Craft::t('other', $row['key'], [], $row['language']), $row['message'], "{$row['key']} ({$row['language']})");
        }

        $this->assertSame([
            'Source language only' => 'In the source language',
            'Translated' => 'Traduit',
            'Base language' => 'Langue de base',
            'Source base language' => 'From the en row',
            'Not translated' => 'Not translated',
        ], $messages['fr-CA']);
    }

    public function testGraphqlQuery(): void
    {
        $this->plugin()->messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);

        $gql = Craft::$app->getGql();
        $result = $gql->executeQuery($gql->getPublicSchema(), '{ staticMessages(language: ["fr-CA"], category: ["site"]) { key message language category } }');

        $this->assertArrayNotHasKey('errors', $result, json_encode($result));
        $this->assertSame([['key' => 'Translated', 'message' => 'Traduit', 'language' => 'fr-CA', 'category' => 'site']], $result['data']['staticMessages']);
    }

    private function sorted(array $values): array
    {
        sort($values);
        return $values;
    }
}
