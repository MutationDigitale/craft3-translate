<?php

namespace mutation\translate\services;

use mutation\translate\events\MessageEvent;
use mutation\translate\helpers\DbHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use yii\base\Component;

class MessagesService extends Component
{
    const EVENT_BEFORE_ADD_MESSAGE = 'beforeAddMessage';
    const EVENT_AFTER_ADD_MESSAGE = 'afterAddMessage';
    const EVENT_BEFORE_SAVE_MESSAGES = 'beforeSaveMessages';
    const EVENT_AFTER_SAVE_MESSAGES = 'afterSaveMessages';
    const EVENT_BEFORE_DELETE_MESSAGES = 'beforeDeleteMessages';
    const EVENT_AFTER_DELETE_MESSAGES = 'afterDeleteMessages';

    public function addMessage($message, $category)
    {
        $this->trigger(self::EVENT_BEFORE_ADD_MESSAGE, new MessageEvent());

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
            $this->trigger(self::EVENT_AFTER_ADD_MESSAGE, new MessageEvent());

            return $sourceMessage;
        }

        return null;
    }

    public function saveMessages($translations)
    {
        $this->trigger(self::EVENT_BEFORE_SAVE_MESSAGES, new MessageEvent());

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

        $this->trigger(self::EVENT_AFTER_SAVE_MESSAGES, new MessageEvent());
    }

    public function deleteMessages($sourceMessageIds)
    {
        $this->trigger(self::EVENT_BEFORE_DELETE_MESSAGES, new MessageEvent());

        foreach ($sourceMessageIds as $sourceMessageId) {
            $sourceMessage = SourceMessage::find()
                ->where(array('id' => $sourceMessageId))
                ->one();

            $sourceMessage->delete();
        }

        $this->trigger(self::EVENT_AFTER_DELETE_MESSAGES, new MessageEvent());
    }
}
