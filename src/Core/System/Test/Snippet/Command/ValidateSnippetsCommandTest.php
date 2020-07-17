<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Symfony\Component\Console\Tester\CommandTester;

class ValidateSnippetsCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(ValidateSnippetsCommand::class));
        $commandTester->execute([]);

        static::assertEquals(0, $commandTester->getStatusCode(), "\"bin/console snippets:validate\" returned errors:\n" . $commandTester->getDisplay());
    }
}
