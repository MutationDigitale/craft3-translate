<?php

namespace mutation\translate\migrations;

use craft\db\Migration;

/**
 * m191016_152459_init migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable(
            '{{%source_message}}',
            [
                'id' => $this->primaryKey(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),

                'category' => $this->string(),
                'message' => $this->text(),
            ],
            $tableOptions
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
            ],
            $tableOptions
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
