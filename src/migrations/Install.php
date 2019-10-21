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

        $this->createTable('{{%source_message}}', [
            'id' => $this->primaryKey(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),

            'category' => $this->string(),
            'message' => $this->text(),
        ], $tableOptions);

        $this->createTable('{{%message}}', [
            'id' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),

            'language' => $this->string(16)->notNull(),
            'translation' => $this->text(),
        ], $tableOptions);

        $this->addPrimaryKey('pk_message_id_language', '{{%message}}', ['id', 'language']);
        $this->addForeignKey('fk_message_source_message', '{{%message}}', 'id', '{{%source_message}}', 'id', 'CASCADE', 'RESTRICT');
        $this->createIndex('idx_source_message_category', '{{%source_message}}', 'category');
        $this->createIndex('idx_message_language', '{{%message}}', 'language');

        $sites = Craft::$app->sites->getAllSites();
        $translations = array();
        foreach ($sites as $site) {
            $path = Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $site->language . DIRECTORY_SEPARATOR . 'site.php';
            $siteTranslations = array();
            if (file_exists($path)) {
                $siteTranslations = include($path);
            }
            foreach ($siteTranslations as $key => $translation) {
                $translations[$key][$site->language] = $translation;
            }
        }

        foreach ($translations as $message => $sites) {
            try {
                $languages = array();
                foreach ($sites as $site => $translation) {
                    $languages[$site] = $translation;
                }

                $sourceMessage = SourceMessage::find()
                    ->where(array('message' => $message, 'category' => 'site'))
                    ->one();

                if (!$sourceMessage) {
                    $sourceMessage = new SourceMessage();
                    $sourceMessage->category = 'site';
                    $sourceMessage->message = $message;
                    $sourceMessage->languages = $languages;
                    $sourceMessage->save();
                }
            } catch (Exception $exception) {

            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_message_source_message', '{{%message}}');
        $this->dropTable('{{%message}}');
        $this->dropTable('{{%source_message}}');
    }
}
