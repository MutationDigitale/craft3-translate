<?php

namespace mutation\translate\tests\unit;

use Craft;
use mutation\translate\migrations\Install;

/**
 * Uninstalling the plugin and installing it again.
 */
class InstallTest extends TestCase
{
    public function testUninstallAndInstallAgain(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site', ['fr-CA' => 'Bonjour']);
        $db = Craft::$app->getDb();

        $this->assertNotFalse((new Install())->down());
        $this->assertNull($db->getTableSchema('{{%source_message}}', true));
        $this->assertNull($db->getTableSchema('{{%message}}', true));

        $this->assertNotFalse((new Install())->up());
        $this->assertTrue($db->columnExists('{{%source_message}}', 'messageHash'));
        $this->assertNotNull($this->plugin()->messages->addMessage('Hello', 'site', ['fr-CA' => 'Bonjour']));
        $this->assertNull($this->plugin()->messages->addMessage('Hello', 'site'), 'The unique index prevents duplicates');
        $this->assertSame('Bonjour', Craft::t('site', 'Hello', [], 'fr-CA'));
    }
}
