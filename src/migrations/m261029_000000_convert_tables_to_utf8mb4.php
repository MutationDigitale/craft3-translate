<?php

namespace mutation\translate\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;

/**
 * Converts the tables to the database charset (utf8mb4 by default), so messages can contain any character (e.g. emojis).
 */
class m261029_000000_convert_tables_to_utf8mb4 extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->getIsMysql()) {
            return true;
        }

        $dbConfig = Craft::$app->getConfig()->getDb();
        $charset = $dbConfig->getCharset();
        $collation = $dbConfig->collation ?? Db::defaultCollation($this->db);

        foreach (['{{%source_message}}', '{{%message}}'] as $table) {
            $tableName = $this->db->getSchema()->getRawTableName($table);

            // Tables created with an old row format have a 767 bytes index limit, too small for utf8mb4 indexes
            $rowFormat = $this->db->createCommand('SELECT [[ROW_FORMAT]] FROM [[information_schema]].[[TABLES]] WHERE [[TABLE_SCHEMA]] = DATABASE() AND [[TABLE_NAME]] = :table', [':table' => $tableName])->queryScalar();
            $rowFormatOption = in_array(strtolower((string)$rowFormat), ['compact', 'redundant'], true) ? 'ROW_FORMAT=DYNAMIC, ' : '';

            $this->execute("ALTER TABLE `{$tableName}` {$rowFormatOption}CONVERT TO CHARACTER SET {$charset} COLLATE {$collation}");
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m261029_000000_convert_tables_to_utf8mb4 cannot be reverted.\n";
        return false;
    }
}
