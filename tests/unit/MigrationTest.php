<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\db\Query;
use mutation\translate\migrations\m261029_000000_convert_tables_to_utf8mb4;

/**
 * Upgrades from the 4.2.3 schema, whose tables were created as utf8 (utf8mb3) on MySQL and MariaDB.
 */
class MigrationTest extends TestCase
{
    protected function _before(): void
    {
        parent::_before();

        if (!$this->isMysql()) {
            $this->markTestSkipped('MySQL and MariaDB only');
        }
    }

    protected function _after(): void
    {
        // Leave the tables as installed for the other tests
        Craft::$app->getDb()->createCommand()->delete('{{%source_message}}')->execute();
        $this->runMigration(new m261029_000000_convert_tables_to_utf8mb4());

        parent::_after();
    }

    public function testConvertToUtf8mb4(): void
    {
        // Like the tables created by 4.2.3, possibly on an old server
        $this->convertTables('ROW_FORMAT=COMPACT, ');
        $long = str_repeat('€', 21845); // 65535 bytes, the maximum of a utf8mb3 TEXT column
        $this->insertRaw('site', 'Accents éàü', ['fr-CA' => 'Traduction éàü']);
        $this->insertRaw('site', $long, ['fr-CA' => 'Long']);
        $before = $this->dump();

        [$success, $output] = $this->runMigration(new m261029_000000_convert_tables_to_utf8mb4());

        $this->assertTrue($success, $output);
        foreach (['source_message', 'message'] as $table) {
            $status = Craft::$app->getDb()->createCommand("SHOW TABLE STATUS LIKE '{$this->rawTable($table)}'")->queryOne();
            $this->assertStringStartsWith('utf8mb4', $status['Collation']);
            $this->assertSame('Dynamic', $status['Row_format']);
        }
        $this->assertSame($before, $this->dump(), 'The data is unchanged');
        $this->assertNotNull($this->plugin()->messages->addMessage('Emoji 🎉', 'other'), 'Any character can be stored');
    }

    /**
     * https://github.com/MutationDigitale/craft3-translate/issues/73: on utf8mb3 tables, a message with emojis can't be stored.
     */
    public function testMessagesWithEmojis(): void
    {
        $message = 'DRX Sport 🇭🇺';
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = true;
        $settings->addMissingSiteRequestOnly = false;

        // Before the migration (or when the database charset is utf8), looking up the message must not fail
        $this->convertTables();
        $this->assertNull($this->plugin()->sourceMessage->getSourceMessageId('site', $message));

        $this->assertTrue($this->runMigration(new m261029_000000_convert_tables_to_utf8mb4())[0]);
        $this->translateInTemplate($message, 'fr-CA');
        $this->assertCount(1, $this->sourceMessageIds($message), 'The message is added with its emoji');
        $this->assertNull($this->plugin()->messages->addMessage($message, 'site'), 'And found again');
    }

    /**
     * Converts the tables to utf8mb3, like the tables created by 4.2.3.
     */
    private function convertTables(string $options = ''): void
    {
        foreach (['source_message', 'message'] as $table) {
            Craft::$app->getDb()->createCommand("ALTER TABLE `{$this->rawTable($table)}` {$options}CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci")->execute();
        }
    }

    /**
     * @return array{bool, string} Whether it succeeded, and its output
     */
    private function runMigration(object $migration): array
    {
        ob_start();
        try {
            $success = $migration->up() !== false;
        } finally {
            $output = ob_get_clean();
        }

        return [$success, $output];
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
