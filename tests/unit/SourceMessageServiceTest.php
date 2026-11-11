<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\db\Query;
use craft\mutex\Mutex;
use mutation\translate\models\SourceMessage;
use mutation\translate\resolvers\StaticMessageResolver;
use yii\mutex\FileMutex;

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

        // E.g. an import: the messages created in the transaction are found again
        $transaction = Craft::$app->getDb()->beginTransaction();
        $this->assertNotNull($service->createSourceMessage('site', 'In transaction'));
        $this->assertNull($service->createSourceMessage('site', 'In transaction'));
        $transaction->commit();

        $this->assertCount(1, $this->sourceMessageIds('In transaction'));
    }

    public function testTheMessageIsLockedWhileItIsAdded(): void
    {
        $config = [
            'class' => Mutex::class,
            'mutex' => ['class' => FileMutex::class, 'mutexPath' => Craft::$app->getPath()->getTempPath() . '/translations-mutex'],
        ];
        $appMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', Craft::createObject($config));
        // Another request adding the same message
        $otherRequest = Craft::createObject($config);
        $lockName = 'translations-admin:message:' . SourceMessage::hash("site\0Locked");
        $service = $this->plugin()->sourceMessage;
        $service->lockTimeout = 0;

        try {
            $this->assertTrue($otherRequest->acquire($lockName));
            $this->assertNull($service->createSourceMessage('site', 'Locked'), 'Not added while another request adds it');
            $this->assertNotNull($service->createSourceMessage('site', 'Other message'), 'The other messages are not locked');

            $otherRequest->release($lockName);
            $this->assertNotNull($service->createSourceMessage('site', 'Locked'));
        } finally {
            $otherRequest->release($lockName);
            Craft::$app->set('mutex', $appMutex);
        }
    }

    public function testDuplicatesOfPreviousVersionsAreTolerated(): void
    {
        // Added more than once by a previous version: the oldest one is used
        $oldest = $this->insertRaw('site', 'Duplicate', ['fr-CA' => 'Ancien']);
        $this->insertRaw('site', 'Duplicate', ['fr-CA' => 'Récent']);
        $service = $this->plugin()->sourceMessage;

        $this->assertSame($oldest, $service->getSourceMessageId('site', 'Duplicate'));
        $this->assertSame([SourceMessage::hash('Duplicate') => $oldest], $service->getSourceMessageIdsByCategory('site'));
        $this->assertNull($this->plugin()->messages->addMessage('Duplicate', 'site'));
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

    public function testGraphqlReturnsTheTranslationRows(): void
    {
        $this->plugin()->messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        $this->plugin()->messages->addMessage('Other category', 'other', ['fr-CA' => 'Autre']);
        $this->insertRaw('site', 'Without translation rows');

        $rows = StaticMessageResolver::resolve(null, ['language' => ['fr-CA', 'en-US'], 'category' => ['site']]);
        $messages = [];
        foreach ($rows as $row) {
            $messages[$row['language']][$row['key']] = $row['message'];
        }

        // Like the previous versions: the existing translation rows, null when they're empty
        ksort($messages);
        $this->assertSame(['en-US' => ['Translated' => null], 'fr-CA' => ['Translated' => 'Traduit']], $messages);
    }

    public function testGraphqlDefaultArguments(): void
    {
        $this->plugin()->messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        $this->plugin()->messages->addMessage('Other category', 'other', ['fr-CA' => 'Autre']);
        $language = Craft::$app->language;
        Craft::$app->language = 'fr-CA';

        try {
            $rows = StaticMessageResolver::resolve(null, []);
        } finally {
            Craft::$app->language = $language;
        }

        // The current language and the site category
        $this->assertSame([['Translated', 'Traduit', 'fr-CA', 'site']], array_map(fn($row) => [$row['key'], $row['message'], $row['language'], $row['category']], $rows));
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
