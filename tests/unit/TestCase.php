<?php

namespace mutation\translate\tests\unit;

use UnitTester;

/**
 * Base class of the plugin tests: two sites (en-US and fr-CA), empty translation tables and caches.
 */
abstract class TestCase extends \craft\test\TestCase
{
    use TranslationsTestTrait;

    protected UnitTester $tester;

    protected function _before(): void
    {
        parent::_before();
        $this->setUpTranslations();
    }

    protected function _after(): void
    {
        $this->removeTranslationFiles();

        parent::_after();
    }
}
