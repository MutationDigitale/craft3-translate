<?php

namespace mutation\filecache\utilities;

use Craft;
use craft\base\Utility;

class CacheUtility extends Utility
{
	public static function displayName(): string
	{
		return Craft::t('filecache', 'File Cache');
	}

	public static function id(): string
	{
		return 'filecache';
	}

	public static function contentHtml(): string
	{
		return Craft::$app->getView()->renderTemplate('filecache/_utility');
	}
}
