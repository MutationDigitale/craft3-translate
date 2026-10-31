<?php

namespace mutation\translate\tests\unit;

use Craft;
use mutation\translate\services\MessagesService;
use Throwable;
use yii\base\Event;

class MessagesServiceTest extends TestCase
{
    public function testAddMessage(): void
    {
        $messages = $this->plugin()->messages;

        $this->assertNotNull($messages->addMessage('Added', 'site', ['fr-CA' => 'Ajouté']));
        $this->assertNull($this->storedTranslation('Added', 'en-US'));
        $this->assertSame('Ajouté', $this->storedTranslation('Added', 'fr-CA'));

        $this->assertNull($messages->addMessage('Added', 'site'), 'A message already in the category is not added again');
        $this->assertNull($messages->addMessage('', 'site'));
        $this->assertCount(1, $this->sourceMessageIds('Added'));
    }

    public function testSaveMessages(): void
    {
        $messages = $this->plugin()->messages;
        $id = $messages->addMessage('Saved', 'site')->id;

        $this->assertTrue($messages->saveMessages(['fr-CA' => [$id => 'Enregistré'], 'en-US' => [$id => '   ']]));
        $this->assertSame('Enregistré', $this->storedTranslation('Saved', 'fr-CA'));
        $this->assertNull($this->storedTranslation('Saved', 'en-US'), 'Blank translations are saved as null');

        $this->assertTrue($messages->saveMessages(['fr-CA' => [$id => '  Espaces  ']]));
        $this->assertSame('  Espaces  ', $this->storedTranslation('Saved', 'fr-CA'), 'Spaces around a translation are kept');

        $this->assertFalse($messages->saveMessages(['xx-XX' => [$id => 'Unknown language']]));
        $this->assertFalse($messages->saveMessages(['fr-CA' => [999999 => 'Unknown message']]));

        $this->assertFalse($messages->saveMessages(['fr-CA' => [$id => 'Partiel', 999999 => 'Unknown message']]));
        $this->assertSame('Partiel', $this->storedTranslation('Saved', 'fr-CA'), 'The translations of existing messages are still saved');
        $this->assertFalse($messages->saveMessages(['fr-CA' => ['abc' => 'Not an ID', $id => 'Valide']]));
        $this->assertSame('Valide', $this->storedTranslation('Saved', 'fr-CA'));
        $this->assertSame(1, $this->countSourceMessages(), 'Nothing is created for unknown IDs');

        $withoutRows = $this->insertRaw('site', 'Without rows');
        $this->assertTrue($messages->saveMessages(['fr-CA' => [$withoutRows => 'Ajouté']]));
        $this->assertSame('Ajouté', $this->storedTranslation('Without rows', 'fr-CA'), 'A missing translation row is created');
        $this->assertFalse($this->storedTranslation('Without rows', 'en-US'), 'Only the saved languages get a row');
    }

    public function testImportOverwrites(): void
    {
        $messages = $this->plugin()->messages;
        $messages->addMessage('Existing', 'site', ['en-US' => 'Existing EN', 'fr-CA' => 'Existant']);
        $messages->addMessage('Untouched', 'site', ['fr-CA' => 'Intact']);

        $result = $messages->importMessages('site', [
            ['message' => 'Existing', 'languages' => ['en-US' => '', 'fr-CA' => 'Modifié', 'xx-XX' => 'Ignored']],
            ['message' => 'New', 'languages' => ['en-US' => 'New EN', 'fr-CA' => '']],
            ['message' => '', 'languages' => ['fr-CA' => 'No key']],
            ['message' => '   ', 'languages' => ['fr-CA' => 'Blank key']],
            ['message' => '007', 'languages' => ['fr-CA' => 'Zéro zéro sept']],
        ]);

        $this->assertSame(['created' => 2, 'added' => 2, 'updated' => 2], $result);
        $this->assertNull($this->storedTranslation('Existing', 'en-US'), 'An empty value clears the translation');
        $this->assertSame('Modifié', $this->storedTranslation('Existing', 'fr-CA'));
        $this->assertSame('New EN', $this->storedTranslation('New', 'en-US'));
        $this->assertSame('Zéro zéro sept', $this->storedTranslation('007', 'fr-CA'));
        $this->assertSame('Intact', $this->storedTranslation('Untouched', 'fr-CA'), 'Messages that are not imported are untouched');
        $this->assertSame(4, $this->countSourceMessages());

        $again = $messages->importMessages('site', [
            ['message' => 'Existing', 'languages' => ['en-US' => '', 'fr-CA' => 'Modifié']],
            ['message' => 'New', 'languages' => ['en-US' => 'New EN', 'fr-CA' => '']],
        ]);
        $this->assertSame(['created' => 0, 'added' => 0, 'updated' => 0], $again, 'Importing the same data again changes nothing');
    }

    public function testImportFillOnly(): void
    {
        $messages = $this->plugin()->messages;
        $messages->addMessage('Partial', 'site', ['en-US' => 'Kept']);

        $result = $messages->importMessages('site', [
            ['message' => 'Partial', 'languages' => ['en-US' => 'Not used', 'fr-CA' => 'Rempli']],
        ], false);

        $this->assertSame(['created' => 0, 'added' => 1, 'updated' => 0], $result);
        $this->assertSame('Kept', $this->storedTranslation('Partial', 'en-US'));
        $this->assertSame('Rempli', $this->storedTranslation('Partial', 'fr-CA'));
    }

    public function testImportWithTheSameKeyMoreThanOnce(): void
    {
        $messages = $this->plugin()->messages;

        $result = $messages->importMessages('site', [
            ['message' => 'Twice', 'languages' => ['en-US' => 'First', 'fr-CA' => 'Premier']],
            ['message' => 'Twice', 'languages' => ['fr-CA' => 'Deuxième']],
        ]);

        $this->assertSame(['created' => 1, 'added' => 2, 'updated' => 0], $result);
        $this->assertSame('First', $this->storedTranslation('Twice', 'en-US'), 'Languages only in the first row are kept');
        $this->assertSame('Deuxième', $this->storedTranslation('Twice', 'fr-CA'), 'The last row wins');
        $this->assertCount(1, $this->sourceMessageIds('Twice'));

        $again = $messages->importMessages('site', [
            ['message' => 'Twice', 'languages' => ['en-US' => 'First', 'fr-CA' => 'Premier']],
            ['message' => 'Twice', 'languages' => ['fr-CA' => 'Deuxième']],
        ]);
        $this->assertSame(['created' => 0, 'added' => 0, 'updated' => 0], $again);
    }

    public function testImportOfMoreMessagesThanALookupBatch(): void
    {
        $messages = $this->plugin()->messages;
        // The existing messages are looked up 1000 at a time: one in each batch
        $messages->addMessage('Message 0005', 'site', ['fr-CA' => 'Ancien']);
        $messages->addMessage('Message 1205', 'site', ['fr-CA' => 'Ancien']);

        $rows = [];
        for ($i = 1; $i <= 1500; $i++) {
            $rows[] = ['message' => sprintf('Message %04d', $i), 'languages' => ['fr-CA' => "Nouveau $i"]];
        }
        $result = $messages->importMessages('site', $rows);

        $this->assertSame(['created' => 1498, 'added' => 1498, 'updated' => 2], $result);
        $this->assertSame(1500, $this->countSourceMessages());
        foreach (['0005' => 'Nouveau 5', '1205' => 'Nouveau 1205', '1500' => 'Nouveau 1500'] as $number => $translation) {
            $this->assertSame($translation, $this->storedTranslation("Message $number", 'fr-CA'));
        }
    }

    public function testDeleteMessages(): void
    {
        $messages = $this->plugin()->messages;
        $deleted = $messages->addMessage('Deleted', 'site', ['fr-CA' => 'Supprimé'])->id;
        $messages->addMessage('Kept', 'site');

        $messages->deleteMessages([$deleted, 999999]);

        $this->assertSame([], $this->sourceMessageIds('Deleted'));
        $this->assertFalse($this->storedTranslation('Deleted', 'fr-CA'));
        $this->assertCount(1, $this->sourceMessageIds('Kept'));

        $messages->deleteAllMessages();
        $this->assertSame(0, $this->countSourceMessages());
    }

    public function testEvents(): void
    {
        $messages = $this->plugin()->messages;
        $fired = [];
        foreach ([MessagesService::EVENT_AFTER_ADD_MESSAGE, MessagesService::EVENT_AFTER_SAVE_MESSAGES, MessagesService::EVENT_AFTER_DELETE_MESSAGES] as $name) {
            Event::on(MessagesService::class, $name, function() use (&$fired, $name) {
                $fired[] = $name;
            });
        }
        $expect = function(array $events, callable $callback) use (&$fired) {
            $fired = [];
            $callback();
            $this->assertSame($events, $fired);
        };

        $expect(['afterAddMessage'], fn() => $messages->addMessage('Event', 'site'));
        $id = $this->sourceMessageIds('Event')[0];
        $expect(['afterSaveMessages'], fn() => $messages->saveMessages(['fr-CA' => [$id => 'x']]));
        $expect(['afterSaveMessages'], fn() => $messages->importMessages('site', [['message' => 'Event', 'languages' => ['fr-CA' => 'y']]]));
        $expect([], fn() => $messages->importMessages('site', [['message' => 'Event', 'languages' => ['fr-CA' => 'y']]]));
        $expect(['afterDeleteMessages'], fn() => $messages->deleteMessages([$id]));
        $expect(['afterDeleteMessages'], fn() => $messages->deleteAllMessages());
    }

    public function testImportIsAllOrNothing(): void
    {
        $messages = $this->plugin()->messages;
        $messages->addMessage('Existing', 'site', ['fr-CA' => 'Avant']);
        $this->failOnValue('BOOM');

        try {
            $messages->importMessages('site', [
                ['message' => 'Existing', 'languages' => ['fr-CA' => 'Après']],
                ['message' => 'New', 'languages' => ['fr-CA' => 'Nouveau']],
                ['message' => 'Failing', 'languages' => ['fr-CA' => 'BOOM']],
            ]);
            $this->fail('The import should have failed');
        } catch (Throwable) {
        } finally {
            $this->removeFailOnValue();
        }

        $this->assertSame('Avant', $this->storedTranslation('Existing', 'fr-CA'));
        $this->assertSame([], $this->sourceMessageIds('New'));
        $this->assertSame(1, $this->countSourceMessages());
    }

    public function testSaveIsAllOrNothing(): void
    {
        $messages = $this->plugin()->messages;
        $first = $messages->addMessage('First', 'site', ['fr-CA' => 'Avant'])->id;
        $second = $messages->addMessage('Second', 'site')->id;
        $this->failOnValue('BOOM');

        try {
            $messages->saveMessages(['fr-CA' => [$first => 'Après', $second => 'BOOM']]);
            $this->fail('The save should have failed');
        } catch (Throwable) {
        } finally {
            $this->removeFailOnValue();
        }

        $this->assertSame('Avant', $this->storedTranslation('First', 'fr-CA'));
    }

    /**
     * Adds a database trigger making the inserts and updates of a translation fail.
     */
    private function failOnValue(string $value): void
    {
        $db = Craft::$app->getDb();
        $table = $db->getSchema()->getRawTableName('{{%message}}');
        $this->removeFailOnValue();

        try {
            if ($db->getIsPgsql()) {
                $db->createCommand("CREATE OR REPLACE FUNCTION translations_fail() RETURNS trigger AS \$\$ BEGIN IF NEW.translation = '{$value}' THEN RAISE EXCEPTION 'fail'; END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql")->execute();
                $db->createCommand("CREATE TRIGGER translations_fail BEFORE INSERT OR UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION translations_fail()")->execute();
            } else {
                foreach (['INSERT', 'UPDATE'] as $event) {
                    $db->createCommand("CREATE TRIGGER translations_fail_{$event} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN IF NEW.translation = '{$value}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fail'; END IF; END")->execute();
                }
            }
        } catch (Throwable $e) {
            $this->markTestSkipped("The database user can't create triggers: {$e->getMessage()}");
        }
    }

    private function removeFailOnValue(): void
    {
        $db = Craft::$app->getDb();
        $table = $db->getSchema()->getRawTableName('{{%message}}');
        if ($db->getIsPgsql()) {
            $db->createCommand("DROP TRIGGER IF EXISTS translations_fail ON {$table}")->execute();
        } else {
            $db->createCommand('DROP TRIGGER IF EXISTS translations_fail_INSERT')->execute();
            $db->createCommand('DROP TRIGGER IF EXISTS translations_fail_UPDATE')->execute();
        }
    }
}
