<?php

namespace mutation\translate\migrations;

use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(
            '{{%source_message}}',
            [
                'id' => $this->primaryKey(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),

                'category' => $this->string(),
                'message' => $this->text(),
                // Computed by the database, see SourceMessage::hash()
                'messageHash' => 'CHAR(32) GENERATED ALWAYS AS (MD5([[message]])) STORED',
            ]
        );

        $this->createTable(
            '{{%message}}',
            [
                'id' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),

                'language' => $this->string(16)->notNull(),
                'translation' => $this->text(),

                'PRIMARY KEY([[id]], [[language]])',
            ]
        );

        $this->addForeignKey(
            'fk_message_source_message',
            '{{%message}}',
            'id',
            '{{%source_message}}',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->createIndex('idx_source_message_category', '{{%source_message}}', 'category');
        $this->createIndex('idx_source_message_category_hash', '{{%source_message}}', ['category', 'messageHash'], true);
        $this->createIndex('idx_message_language', '{{%message}}', 'language');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        if ($this->db->getTableSchema('{{%message}}', true) !== null) {
            $this->dropForeignKey('fk_message_source_message', '{{%message}}');
        }
        $this->dropTableIfExists('{{%message}}');
        $this->dropTableIfExists('{{%source_message}}');
    }
}
