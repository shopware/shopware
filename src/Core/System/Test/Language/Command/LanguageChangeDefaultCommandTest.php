<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Language\Command;

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LanguageChangeDefaultCommandTest extends KernelTestCase
{
    use IntegrationTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['locale' => 'de-DE'], ['interactive' => true]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console language:change-default\" returned errors:\n" . $commandTester->getDisplay()
        );

        static::assertStringContainsString(
            'system default language changed to de-DE',
            $commandTester->getDisplay()
        );
    }

    public function testNoExecution(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->setInputs(['no']);
        $commandTester->execute(['locale' => 'de-DE'], ['interactive' => true]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console language:change-default\" returned errors:\n" . $commandTester->getDisplay()
        );

        static::assertStringNotContainsString(
            'system default language changed to de-DE',
            $commandTester->getDisplay()
        );
    }

    public function testUnknownLocaleReturnsError(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['locale' => 'unknown'], ['interactive' => true]);

        static::assertEquals(
            1,
            $commandTester->getStatusCode()
        );

        static::assertStringContainsString(
            'argument locale isn\'t a valid locale code',
            $commandTester->getDisplay()
        );
    }

    public function testEmptyLocaleShouldStartInteract(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->setInputs(['German', 'de-DE', 'yes']);
        $commandTester->execute([], ['interactive' => true]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode()
        );

        static::assertStringContainsString(
            'Please choose a language?',
            $commandTester->getDisplay()
        );

        static::assertStringContainsString(
            ' default language changed to de-DE',
            $commandTester->getDisplay()
        );
    }

    private function getCommandTester(): CommandTester
    {
        $kernel = $this->getContainer()->get('kernel');
        $application = new Application($kernel);

        $command = $application->find('language:change-default');

        return new CommandTester($command);
    }
}
