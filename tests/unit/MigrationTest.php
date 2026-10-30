<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\db\Query;
use mutation\translate\migrations\m261029_000000_convert_tables_to_utf8mb4;
use mutation\translate\migrations\m261029_000001_add_message_hash;

/**
 * Upgrades from the 4.2.3 schema: the duplicates must be merged without losing data.
 */
class MigrationTest extends TestCase
{
    protected function _before(): void
    {
        parent::_before();

        // Back to the 4.2.3 schema: no hash column and no unique index
        $this->runMigration(new m261029_000001_add_message_hash(), 'down');
    }

    protected function _after(): void
    {
        // Leave the schema as installed for the other tests
        Craft::$app->getDb()->createCommand()->delete('{{%source_message}}')->execute();
        if (!Craft::$app->getDb()->columnExists('{{%source_message}}', 'messageHash')) {
            $this->runMigration(new m261029_000001_add_message_hash(), 'up');
        }

        parent::_after();
    }

    public function testDuplicatesAreMerged(): void
    {
        $date = fn(int $year) => "$year-06-01 12:00:00";
        // Identical translations
        $identical = $this->insertRaw('site', 'Identical', ['en-US' => 'Same', 'fr-CA' => 'Pareil'], $date(2020));
        $this->insertRaw('site', 'Identical', ['en-US' => 'Same', 'fr-CA' => 'Pareil'], $date(2021));
        // Conflicts: the most recently updated translation wins, whichever row it's in
        $newestWins = $this->insertRaw('site', 'Newest updated last', ['fr-CA' => 'Ancien'], $date(2020));
        $this->insertRaw('site', 'Newest updated last', ['fr-CA' => 'Nouveau'], $date(2024));
        $oldestWins = $this->insertRaw('site', 'Oldest updated last', ['fr-CA' => 'Modifié récemment'], $date(2025));
        $this->insertRaw('site', 'Oldest updated last', ['fr-CA' => 'Périmé'], $date(2021));
        // An empty translation never replaces a filled one
        $emptyNewest = $this->insertRaw('site', 'Empty newest', ['en-US' => 'Kept EN', 'fr-CA' => 'Gardé'], $date(2020));
        $this->insertRaw('site', 'Empty newest', ['en-US' => null, 'fr-CA' => '   '], $date(2024));
        // Three rows filling different languages, one not used by a site anymore
        $three = $this->insertRaw('site', 'Three', ['en-US' => 'Three EN', 'fr-CA' => null], $date(2020));
        $this->insertRaw('site', 'Three', ['en-US' => null, 'fr-CA' => 'Trois'], $date(2021));
        $this->insertRaw('site', 'Three', ['de' => 'Drei'], $date(2022));
        // No translation rows at all
        $noRows = $this->insertRaw('other', 'No rows');
        $this->insertRaw('other', 'No rows');
        // Same timestamps: the newest row wins
        $tie = $this->insertRaw('site', 'Tie', ['fr-CA' => 'Premier'], $date(2022));
        $this->insertRaw('site', 'Tie', ['fr-CA' => 'Second'], $date(2022));
        // The same message in another category is merged separately
        $otherCategory = $this->insertRaw('other', 'Identical', ['fr-CA' => 'Autre catégorie'], $date(2020));
        $this->insertRaw('other', 'Identical', ['fr-CA' => 'Autre catégorie 2'], $date(2023));
        // Not duplicates
        $notDuplicates = [];
        foreach (['Case' => 'Majuscule', 'case' => 'minuscule', 'Pad' => 'Sans espace', 'Pad ' => 'Avec espace', 'Accent é' => 'Avec accent', 'Accent e' => 'Sans accent'] as $message => $translation) {
            $notDuplicates[$this->insertRaw('site', $message, ['fr-CA' => $translation])] = $translation;
        }
        // Rows the unique index doesn't cover
        $nulls = [
            $this->insertRaw('site', null, ['fr-CA' => 'Null message 1']),
            $this->insertRaw('site', null, ['fr-CA' => 'Null message 2']),
            $this->insertRaw(null, 'Null category', ['fr-CA' => 'Null category 1']),
            $this->insertRaw(null, 'Null category', ['fr-CA' => 'Null category 2']),
        ];

        [$success, $output] = $this->runMigration(new m261029_000001_add_message_hash(), 'up');

        $this->assertTrue($success, $output);
        $expected = [
            [$identical, 'Identical', 'site', ['en-US' => 'Same', 'fr-CA' => 'Pareil']],
            [$newestWins, 'Newest updated last', 'site', ['fr-CA' => 'Nouveau']],
            [$oldestWins, 'Oldest updated last', 'site', ['fr-CA' => 'Modifié récemment']],
            [$emptyNewest, 'Empty newest', 'site', ['en-US' => 'Kept EN', 'fr-CA' => 'Gardé']],
            [$three, 'Three', 'site', ['de' => 'Drei', 'en-US' => 'Three EN', 'fr-CA' => 'Trois']],
            [$noRows, 'No rows', 'other', []],
            [$tie, 'Tie', 'site', ['fr-CA' => 'Second']],
            [$otherCategory, 'Identical', 'other', ['fr-CA' => 'Autre catégorie 2']],
        ];
        foreach ($expected as [$keptId, $message, $category, $translations]) {
            $this->assertSame([$keptId], $this->sourceMessageIds($message, $category), "$message ($category): only the oldest row is kept");
            $this->assertSame($translations, $this->translations($keptId), "$message ($category)");
        }
        foreach ($notDuplicates as $id => $translation) {
            $this->assertSame(['fr-CA' => $translation], $this->translations($id));
        }
        foreach ($nulls as $id) {
            $this->assertCount(1, $this->translations($id), 'Rows with a NULL message or category are untouched');
        }
        $this->assertSame(8 + 6 + 4, $this->countSourceMessages());

        // Every translation that was dropped is in the output (and the logs), so it can be recovered
        foreach (['Ancien', 'Périmé', 'Premier', 'Autre catégorie'] as $dropped) {
            $this->assertStringContainsString("translation \"{$dropped}\"", $output);
        }
        $this->assertSame(4, substr_count($output, 'dropped the'));
        $this->assertStringContainsString('merged 9 duplicate source message(s), 4 conflicting translation(s)', $output);

        $this->assertTrue(Craft::$app->getDb()->columnExists('{{%source_message}}', 'messageHash'));
        $this->assertNull($this->plugin()->messages->addMessage('Identical', 'site'), 'Duplicates are now prevented');
    }

    public function testTheMigrationCanBeRunAgainAfterAFailure(): void
    {
        $this->insertRaw('site', 'Kept', ['fr-CA' => 'Gardé']);
        $this->runMigration(new m261029_000001_add_message_hash(), 'up');
        // MySQL commits each table change, so a failure can leave the column without the index
        Craft::$app->getDb()->createCommand()->dropIndex('idx_source_message_category_hash', '{{%source_message}}')->execute();
        $this->insertRaw('site', 'Kept', ['fr-CA' => 'Ajouté pendant la panne']);

        $this->assertTrue($this->runMigration(new m261029_000001_add_message_hash(), 'up')[0]);
        $this->assertSame(1, $this->countSourceMessages());
        $this->assertTrue($this->runMigration(new m261029_000001_add_message_hash(), 'up')[0], 'Running it once more changes nothing');
    }

    public function testConflictingCategoriesStopTheMigrationWithoutChanges(): void
    {
        if (!$this->isMysql()) {
            $this->markTestSkipped('Only MySQL and MariaDB compare the categories case-insensitively');
        }

        $lowercase = $this->insertRaw('site', 'Same message', ['fr-CA' => 'Minuscule']);
        $uppercase = $this->insertRaw('Site', 'Same message', ['fr-CA' => 'Majuscule']);
        $this->insertRaw('site', 'Duplicate', ['fr-CA' => 'Un']);
        $this->insertRaw('site', 'Duplicate', ['fr-CA' => 'Deux']);
        $before = $this->dump();

        [$success, $output] = $this->runMigration(new m261029_000001_add_message_hash(), 'up');

        $this->assertFalse($success);
        $this->assertStringContainsString("\"Same message\" in site (#{$lowercase}), Site (#{$uppercase})", $output, 'Each row is listed with its category, to fix it');
        $this->assertStringNotContainsString('dropped', $output, "The merge that is rolled back isn't reported");
        $this->assertStringContainsString('Nothing was changed', $output);
        $this->assertSame($before, $this->dump(), 'The duplicates merged before the check were rolled back');
        $this->assertFalse(Craft::$app->getDb()->columnExists('{{%source_message}}', 'messageHash'));
    }

    public function testConvertToUtf8mb4(): void
    {
        if (!$this->isMysql()) {
            $this->markTestSkipped('MySQL and MariaDB only');
        }

        // Like the tables created by 4.2.3, possibly on an old server
        $db = Craft::$app->getDb();
        foreach (['source_message', 'message'] as $table) {
            $db->createCommand("ALTER TABLE `{$this->rawTable($table)}` ROW_FORMAT=COMPACT, CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci")->execute();
        }
        $long = str_repeat('€', 21845); // 65535 bytes, the maximum of a utf8mb3 TEXT column
        $this->insertRaw('site', 'Accents éàü', ['fr-CA' => 'Traduction éàü']);
        $this->insertRaw('site', $long, ['fr-CA' => 'Long']);
        $before = $this->dump();

        [$success, $output] = $this->runMigration(new m261029_000000_convert_tables_to_utf8mb4(), 'up');

        $this->assertTrue($success, $output);
        foreach (['source_message', 'message'] as $table) {
            $status = $db->createCommand("SHOW TABLE STATUS LIKE '{$this->rawTable($table)}'")->queryOne();
            $this->assertStringStartsWith('utf8mb4', $status['Collation']);
            $this->assertSame('Dynamic', $status['Row_format']);
        }
        $this->assertSame($before, $this->dump(), 'The data is unchanged');
        $this->assertTrue($this->runMigration(new m261029_000001_add_message_hash(), 'up')[0]);
        $this->assertNotNull($this->plugin()->messages->addMessage('Emoji 🎉', 'other'), 'Any character can be stored');
    }

    /**
     * https://github.com/MutationDigitale/craft3-translate/issues/73: on utf8mb3 tables, MySQL (without strict mode) stores
     * a message with emojis as "??", so it was never found again and added on every request.
     */
    public function testMessagesWithEmojisAreNotAddedOnEveryRequest(): void
    {
        if (!$this->isMysql()) {
            $this->markTestSkipped('MySQL and MariaDB only');
        }

        $db = Craft::$app->getDb();
        $sqlMode = $db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar();
        $message = 'DRX Sport 🇭🇺';

        try {
            $db->createCommand("SET SESSION sql_mode = ''")->execute();
            foreach (['source_message', 'message'] as $table) {
                $db->createCommand("ALTER TABLE `{$this->rawTable($table)}` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci")->execute();
            }

            // What 4.2.3 did on each request: look up the exact message, and add it when it isn't found
            for ($i = 0; $i < 3; $i++) {
                $found = (new Query())->from('{{%source_message}}')->where(['BINARY(`message`)' => $message, 'category' => 'site'])->exists();
                if (!$found) {
                    $this->insertRaw('site', $message, ['fr-CA' => null]);
                }
            }
            $this->assertSame(3, $this->countSourceMessages(['message' => 'DRX Sport ??']), 'The issue is reproduced');

            $this->assertTrue($this->runMigration(new m261029_000000_convert_tables_to_utf8mb4(), 'up')[0]);
            $this->assertTrue($this->runMigration(new m261029_000001_add_message_hash(), 'up')[0]);
            $this->assertSame(1, $this->countSourceMessages(), 'The existing duplicates are merged');

            $settings = $this->plugin()->getSettings();
            $settings->addMissingTranslations = true;
            $settings->addMissingSiteRequestOnly = false;
            for ($i = 0; $i < 3; $i++) {
                $this->translateInTemplate($message, 'fr-CA');
            }
            $this->assertCount(1, $this->sourceMessageIds($message), 'The message is added once, with its emoji');
            $this->assertSame(2, $this->countSourceMessages(['like', 'message', 'DRX Sport']), 'The "??" row from before the update is the only other one');

            // When the database charset is configured as utf8mb3, the tables aren't converted, but the unique index still prevents duplicates
            $db->createCommand()->delete('{{%source_message}}')->execute();
            foreach (['source_message', 'message'] as $table) {
                $db->createCommand("ALTER TABLE `{$this->rawTable($table)}` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci")->execute();
            }
            for ($i = 0; $i < 3; $i++) {
                $this->translateInTemplate('Other 🎉', 'fr-CA');
            }
            $this->assertSame(1, $this->countSourceMessages(['message' => 'Other ?']));
        } finally {
            $db->createCommand('SET SESSION sql_mode = :mode', [':mode' => $sqlMode])->execute();
            $db->createCommand()->delete('{{%source_message}}')->execute();
            $this->runMigration(new m261029_000000_convert_tables_to_utf8mb4(), 'up');
        }
    }

    /**
     * @return array{bool, string} Whether it succeeded, and its output
     */
    private function runMigration(object $migration, string $direction): array
    {
        ob_start();
        try {
            $success = $migration->$direction() !== false;
        } finally {
            $output = ob_get_clean();
        }

        return [$success, $output];
    }

    private function translations(int $id): array
    {
        $translations = (new Query())->select(['translation', 'language'])->from('{{%message}}')->where(['id' => $id])->indexBy('language')->column();
        ksort($translations);

        return array_filter($translations, fn($translation) => $translation !== null && trim($translation) !== '');
    }

    private function dump(): array
    {
        return [
            (new Query())->select(['id', 'category', 'message'])->from('{{%source_message}}')->orderBy('id')->all(),
            (new Query())->select(['id', 'language', 'translation'])->from('{{%message}}')->orderBy(['id' => SORT_ASC, 'language' => SORT_ASC])->all(),
        ];
    }

    private function rawTable(string $table): string
    {
        return Craft::$app->getDb()->getSchema()->getRawTableName("{{%{$table}}}");
    }
}
