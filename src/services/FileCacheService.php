<?php

namespace mutation\filecache\services;

use craft\base\Component;
use yii\db\Query;

class FileCacheService extends Component
{
	public function getTemplateCacheKeyById($id)
	{
		return (new Query())
			->select('cacheKey')
			->from('{{%templatecaches}}')
			->where([
				'and',
				[
					'id' => $id,
				],
			])
			->scalar();
	}


	public function isCacheable()
	{
		return true;
	}

	public function writeCache($cacheFilePath, $html)
	{
		if (!file_exists($cacheFilePath)) {
			if (!file_exists(dirname($cacheFilePath))) {
				mkdir(dirname($cacheFilePath), 0775, true);
			}
			$file = fopen($cacheFilePath, 'wb');
			fclose($file);
		}

		file_put_contents($cacheFilePath, trim($html));
	}

	public function deleteCache($cacheFilePath)
	{
		if (file_exists($cacheFilePath)) {
			unlink($cacheFilePath);
		}
	}

	public function getCacheFilePath($site, $path)
	{
		$pathSegments = [
			CRAFT_BASE_PATH,
			"web/filecache",
			$site,
			$path
		];

		$targetPath = $this->normalizePath(implode('/', $pathSegments));

		$pathInfo = pathinfo($targetPath);
		$extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : 'html';

		return $targetPath . DIRECTORY_SEPARATOR . 'index.' . $extension;
	}

	private function normalizePath($path)
	{
		$path = preg_replace('#https?://#', '', $path);
		return rtrim(preg_replace('~/+~', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
	}
}
