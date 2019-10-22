<?php

namespace mutation\filecache\models;

use Craft;
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
	public $cacheFolderPath = 'web/filecache';

	/**
	 * @var bool
	 */
	public $automaticallyWarmCache = false;

	/**
	 * @var int
	 */
	public $concurrency = 5;

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
	 * @var array
	 */
	public $excludedEntrySectionsFromWarming = [];

	/**
	 * @var array
	 */
	public $excludedEntryTypesFromWarming = [];

	/**
	 * @var array
	 */
	public $excludedSitesFromWarming = [];
}
