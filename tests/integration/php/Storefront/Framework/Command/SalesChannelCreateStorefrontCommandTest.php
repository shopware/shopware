<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @package system-settings
 *
 * @internal
 */
class SalesChannelCreateStorefrontCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExecuteCommandSuccessfully(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelCreateStorefrontCommand::class));
        $url = 'http://localhost';

        $commandTester->execute([
            '--name' => 'Storefront',
            '--url' => $url,
            '--isoCode' => 'de_DE',
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteWithException(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelCreateStorefrontCommand::class));
        $url = 'http://localhost';

        $this->expectExceptionMessage('Unable to get default SnippetSet. Please provide a valid SnippetSetId.');

        $commandTester->execute([
            '--name' => 'Storefront',
            '--url' => $url,
            '--isoCode' => 'xy-XY',
        ]);
    }
}
