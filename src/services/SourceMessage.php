<?php

namespace mutation\translate\services;

use Craft;
use craft\db\Query;
use yii\base\Component;

class SourceMessage extends Component
{
    public function getSourceMessagesArrayByCategory($category)
    {
        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);

        $rows = (new Query())
            ->from('{{%source_message}} AS s')
            ->innerJoin('{{%message}} AS m', 'm.id = s.id')
            ->where(['s.category' => $category])
            ->limit(null)
            ->all();

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['id']][] = $row;
        }

        $sourceMessages = [];
        foreach ($groups as $group) {
            $languages = [];
            foreach ($group as $item) {
                $languages[$item['language']] = $item['translation'];
            }
            if (count($languages) < count($siteLocales)) {
                foreach ($siteLocales as $siteLocale) {
                    if (!array_key_exists($siteLocale->id, $languages)) {
                        $languages[$siteLocale->id] = '';
                    }
                }
            }
            $sourceMessages[] = [
                'id' => $group[0]['id'],
                'message' => $group[0]['message'],
                'languages' => $languages
            ];
        }

        return $sourceMessages;
    }
}
