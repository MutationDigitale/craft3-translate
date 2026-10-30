<?php

namespace mutation\translate\models;

use craft\db\ActiveRecord;

/**
 * This is the model class for table "message".
 *
 * @property int $id
 * @property string $language
 * @property string $translation
 *
 * @property SourceMessage $id0
 */
class Message extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%message}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'language'], 'required'],
            [['id'], 'integer'],
            [['translation'], 'string'],
            [['language'], 'string', 'max' => 16],
            [
                ['id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => SourceMessage::class,
                'targetAttribute' => ['id' => 'id'],
            ],
        ];
    }

    /**
     * Returns a translation as it's stored: a blank translation is null, the other ones are kept as is (e.g. with a trailing space).
     */
    public static function normalizeTranslation($translation): ?string
    {
        if ($translation === null || trim((string)$translation) === '') {
            return null;
        }

        return (string)$translation;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'language' => 'Language',
            'translation' => 'Translation',
        ];
    }
}
