<?php

namespace mutation\translate\migrations;

use Craft;
use craft\db\Migration;
use Exception;
use mutation\translate\models\SourceMessage;

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

        // Support table creation without primary key when sql_require_primary_key
        // is enabled (like on DigitalOcean Managed Databases)
        // https://docs.digitalocean.com/products/databases/mysql/how-to/create-primary-keys/
        if ($this->db->driverName === 'mysql') {
            try {
                $requirePrimaryKey = $this->db->createCommand('SHOW SESSION VARIABLES LIKE "sql_require_primary_key";')->queryAll();
                if (isset($requirePrimaryKey[0]['Value']) && $requirePrimaryKey[0]['Value'] === 'ON') {
                    $this->execute('SET SESSION sql_require_primary_key = 0');
                }
            } catch (\yii\db\Exception $e) {
            }
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
            ],
            $tableOptions
        );

        $this->addPrimaryKey('pk_message_id_language', '{{%message}}', ['id', 'language']);
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
