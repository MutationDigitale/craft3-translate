<?php

namespace mutation\translate\models;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * This is the model class for table "source_message".
 *
 * @property int $id
 * @property string $category
 * @property string $message
 *
 * @property Message[] $messages
 */
class SourceMessage extends ActiveRecord
{
    /**
     * @var array<string, string|null> The translations, indexed by site language, saved with the model
     */
    public $languages;

    public function __construct($config = [])
    {
        $this->languages = [];
        foreach (Craft::$app->getI18n()->getSiteLocales() as $locale) {
            $this->languages[$locale->id] = null;
        }
        parent::__construct($config);
    }

    public static function tableName()
    {
        return '{{%source_message}}';
    }

    /**
     * Returns a hash of a message, to index messages by their exact (case and accent sensitive) value.
     *
     * @internal
     */
    public static function hash(string $message): string
    {
        return md5($message);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['message', 'string'],
            ['category', 'string', 'max' => 255],
            ['languages', 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'category' => 'category',
            'message' => 'key',
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getMessages()
    {
        return $this->hasMany(Message::class, ['id' => 'id']);
    }

    public function getTranslations()
    {
        // Not ArrayHelper::map(): with objects, recent Yii versions skip the null translations
        $translations = [];
        foreach ($this->messages as $message) {
            $translations[$message->language] = $message->translation;
        }
        return $translations;
    }

    public function getTranslation($lang)
    {
        return (isset($this->translations[$lang])) ? $this->translations[$lang] : null;
    }

    public function afterFind()
    {
        parent::afterFind();

        foreach ($this->languages as $language => $translation) {
            $this->languages[$language] = $this->getTranslation($language);
        }
    }

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        Message::deleteAll(['id' => $this->id]);
        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            foreach ($this->languages as $language => $translation) {
                $message = new Message();
                $message->id = $this->id;
                $message->language = $language;
                $message->translation = Message::normalizeTranslation($translation);
                $message->save();
            }
        } else {
            foreach ($this->messages as $message) {
                $message->translation = Message::normalizeTranslation($this->languages[$message->language] ?? null);
                $message->save();
            }
        }

        parent::afterSave($insert, $changedAttributes);
    }
}
