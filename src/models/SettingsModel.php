<?php

namespace mutation\filecache\models;

use craft\base\Model;

class SettingsModel extends Model
{
	/**
	 * @var bool
	 */
	public $cacheEnabled = true;

	/**
	 * @var string
	 */
	public $cacheFolderPath = 'filecache';

	/**
	 * @var array
	 */
	public $excludedEntrySections = [];

	/**
	 * @var array
	 */
	public $excludedEntryTypes = [];

	/**
	 * @var array
	 */
	public $excludedSites = [];

	/**
	 * @var string
	 */
	public $csrfInputKey = '{$CSRF_INPUT}';

	/**
	 * @var string
	 */
	public $csrfJsTokenKey = '{$CSRF_JS_TOKEN}';
}
