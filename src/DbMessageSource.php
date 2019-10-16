<?php

namespace mutation\translate;

use Craft;
use Yii;

class DbMessageSource extends \yii\i18n\DbMessageSource
{
	public $fallback = [];
	public $useMultiSiteTranslationFeature = false;
	protected $fallbackClass;

	/**
	 * Return something for everything which is requested
	 *
	 * @param string $name
	 *
	 * @return mixed|null
	 */
	public function __get($name)
	{
		try {
			return parent::__get($name);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		try {
			parent::__set($name, $value);
		} catch (\Exception $e) {
			$this->$name = $value;
		}
	}

	public function translateFallback($category, $message, $language)
	{
		if ($this->useMultiSiteTranslationFeature && Craft::$app->getRequest()->getIsSiteRequest()) {
			$this->useMultiSiteTranslationFeature = false;
			$translated = $this->translate($category, $message, $language);
			$this->useMultiSiteTranslationFeature = true;
			if ($translated) {
				return $translated;
			}
		}
		if (count($this->fallback) === 0) {
			return false;
		}
		if (!$this->fallbackClass) {
			$this->fallbackClass = Yii::createObject($this->fallback);
		}

		return $this->fallbackClass->translate($category, $message, $language);
	}

	protected function loadMessages($category, $language)
	{
		if ($this->useMultiSiteTranslationFeature && Craft::$app->getRequest()->getIsSiteRequest()) {
			$category .= '_' . Craft::$app->getSites()->currentSite->handle;
		}

		if ($this->enableCaching) {
			$key = [
				__CLASS__,
				$category,
				$language,
			];
			$messages = $this->cache->get($key);
			if ($messages === false) {
				$messages = $this->loadMessagesFromDb($category, $language);
				$this->cache->set($key, $messages, $this->cachingDuration);
			}

			return $messages;
		}

		return $this->loadMessagesFromDb($category, $language);
	}
}
