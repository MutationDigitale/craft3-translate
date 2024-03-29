<?php

namespace mutation\translate\services;

use mutation\translate\helpers\DbHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use yii\base\Component;
use yii\base\Event;

class MessagesService extends Component
{
    const EVENT_AFTER_ADD_MESSAGE = 'afterAddMessage';
    const EVENT_AFTER_SAVE_MESSAGES = 'afterSaveMessages';
    const EVENT_AFTER_DELETE_MESSAGES = 'afterDeleteMessages';

    public function addMessage($message, $category)
    {
        $sourceMessage = SourceMessage::find()
            ->where(array(DbHelper::caseSensitiveComparisonString('message') => $message, 'category' => $category))
            ->one();

        if ($sourceMessage) {
            return null;
        }

        $sourceMessage = new SourceMessage();
        $sourceMessage->category = $category;
        $sourceMessage->message = $message;

        if ($sourceMessage->save()) {
            $this->trigger(self::EVENT_AFTER_ADD_MESSAGE, new Event());

            return $sourceMessage;
        }

        return null;
    }

    public function saveMessages($translations)
    {
        foreach ($translations as $localeId => $item) {
            foreach ($item as $id => $translation) {
                $message = Message::find()
                    ->where(array('language' => $localeId, 'id' => $id))
                    ->one();

                if (!$message) {
                    $message = new Message();
                    $message->id = $id;
                    $message->language = $localeId;
                }

                $message->translation = trim($translation) !== '' ? $translation : null;
                $message->save();
            }
        }

        $this->trigger(self::EVENT_AFTER_SAVE_MESSAGES, new Event());
    }

    public function deleteMessages($sourceMessageIds)
    {
        foreach ($sourceMessageIds as $sourceMessageId) {
            $sourceMessage = SourceMessage::find()
                ->where(array('id' => $sourceMessageId))
                ->one();

            $sourceMessage->delete();
        }

        $this->trigger(self::EVENT_AFTER_DELETE_MESSAGES, new Event());
    }
}
