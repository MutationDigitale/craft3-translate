<?php

namespace mutation\filecache\variables;

use mutation\filecache\FileCachePlugin;
use mutation\filecache\models\SettingsModel;

class FileCacheVariable
{
	public function injectCsrfInput(): string
	{
		/** @var SettingsModel $settings */
		$settings = FileCachePlugin::$plugin->getSettings();

		return $settings->csrfInputKey;
	}

	public function injectJsCsrfToken(): string
	{
		/** @var SettingsModel $settings */
		$settings = FileCachePlugin::$plugin->getSettings();

		return $settings->csrfJsTokenKey;
	}
}
