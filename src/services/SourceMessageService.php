<?php

namespace mutation\translate\services;

use Craft;
use craft\db\Query;
use yii\base\Component;

class SourceMessageService extends Component
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
                if (!in_array($item['language'], $siteLocales)) {
                    continue;
                }
                $languages[$item['language']] = $item['translation'];
            }
            if (count($languages) < count($siteLocales)) {
                foreach ($siteLocales as $siteLocale) {
                    if (!array_key_exists($siteLocale->id, $languages)) {
                        $languages[$siteLocale->id] = '';
                    }
                }
            }

            ksort($languages);

            $sourceMessages[] = [
                'id' => $group[0]['id'],
                'dateCreated' => $group[0]['dateCreated'],
                'message' => $group[0]['message'],
                'languages' => $languages
            ];
        }

        return $sourceMessages;
    }

    public function getAllSourceMessages()
    {
        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);

        $rows = (new Query())
            ->from('{{%source_message}} AS s')
            ->innerJoin('{{%message}} AS m', 'm.id = s.id')
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
            foreach ($languages as $key => $language) {
                $sourceMessages[$key][$group[0]['category']][$group[0]['message']] = $language === null ? '' : $language;
            }
        }

        return $sourceMessages;
    }

    public function getSourceMessagesByLanguagesAndCategories($languages, $categories)
    {
        $rows = (new Query())
            ->from('{{%source_message}} AS s')
            ->innerJoin('{{%message}} AS m', 'm.id = s.id')
            ->where(['s.category' => $categories, 'm.language' => $languages])
            ->limit(null)
            ->all();

        $sourceMessages = [];
        foreach ($rows as $row) {
            $sourceMessages[] = [
                "key" => $row["message"],
                "message" => $row["translation"],
                'language' => $row['language'],
                'category' => $row['category'],
                'dateCreated' => $row['dateCreated'],
            ];
        }

        return $sourceMessages;
    }
}
