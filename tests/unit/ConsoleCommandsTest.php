<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\helpers\FileHelper;
use mutation\translate\console\controllers\UtilitiesController;
use yii\console\ExitCode;

/**
 * The `translations-admin/utilities/*` commands.
 *
 * Craft's `ConsoleTest` always gets a web application with Codeception 5, so the controller is run directly, with its
 * output captured and its confirmation answered.
 */
class ConsoleCommandsTest extends TestCase
{
    public function testExportAndImport(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site', ['fr-CA' => 'Bonjour']);

        $export = $this->command();
        $this->assertSame(ExitCode::OK, $export->runAction('export'));
        $this->assertSame(['2 translation(s) exported to PHP files.' . PHP_EOL], $export->stdout);

        $this->plugin()->messages->deleteAllMessages();

        $import = $this->command();
        $this->assertSame(ExitCode::OK, $import->runAction('import'));
        $this->assertSame(['1 translation(s) imported to the database.' . PHP_EOL], $import->stdout);
        $this->assertSame('Bonjour', $this->storedTranslation('Hello', 'fr-CA'));
    }

    public function testParseTemplates(): void
    {
        $missing = $this->command();
        $this->assertSame(ExitCode::OK, $missing->runAction('missing'));
        $this->assertSame(['10 translation(s) imported.' . PHP_EOL], $missing->stdout);
        $this->assertCount(1, $this->sourceMessageIds('Parse default'));
    }

    public function testFailuresReturnAnErrorExitCode(): void
    {
        FileHelper::writeToFile(Craft::$app->getPath()->getSiteTranslationsPath() . '/fr-CA/site.php', '<?php return [ broken');

        $import = $this->command();
        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $import->runAction('import'));
        $this->assertSame(['Translations couldn’t be imported to the database.' . PHP_EOL], $import->stderr);
    }

    public function testDeleteAsksForConfirmation(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site');

        $declined = $this->command(false);
        $this->assertSame(ExitCode::OK, $declined->runAction('delete'));
        $this->assertSame(['Are you sure you want to delete all database translations? This action is irreversible.'], $declined->questions);
        $this->assertSame(1, $this->countSourceMessages());

        $confirmed = $this->command(true);
        $this->assertSame(ExitCode::OK, $confirmed->runAction('delete'));
        $this->assertSame(['All translations deleted.' . PHP_EOL], $confirmed->stdout);
        $this->assertSame(0, $this->countSourceMessages());
    }

    private function command(bool $confirm = true): UtilitiesController
    {
        $controller = new class('utilities', $this->plugin()) extends UtilitiesController {
            public array $stdout = [];
            public array $stderr = [];
            public array $questions = [];
            public bool $answer = true;

            public function stdout($string)
            {
                $this->stdout[] = $string;
                return strlen($string);
            }

            public function stderr($string)
            {
                $this->stderr[] = $string;
                return strlen($string);
            }

            public function confirm($message, $default = false)
            {
                $this->questions[] = $message;
                return $this->answer;
            }
        };
        $controller->answer = $confirm;

        return $controller;
    }
}
