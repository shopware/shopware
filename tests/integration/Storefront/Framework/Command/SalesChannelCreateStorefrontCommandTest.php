<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('buyers-experience')]
class SalesChannelCreateStorefrontCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    #[DataProvider('dataProviderTestExecuteCommandSuccess')]
    public function testExecuteCommandSuccessfully(string $isoCode, string $isoCodeExpected): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelCreateStorefrontCommand::class));
        $url = 'http://localhost/' . Uuid::randomHex();

        $commandTester->execute([
            '--name' => 'Storefront',
            '--url' => $url,
            '--isoCode' => $isoCode,
        ]);

        $saleChannelId = $commandTester->getInput()->getOption('id');

        $countSaleChannelId = $this->connection->fetchOne('SELECT COUNT(id) FROM sales_channel WHERE id = :id', ['id' => Uuid::fromHexToBytes($saleChannelId)]);

        static::assertEquals(1, $countSaleChannelId);

        $getIsoCodeSql = <<<'SQL'
            SELECT snippet_set.iso
            FROM sales_channel_domain
            JOIN snippet_set ON snippet_set.id = sales_channel_domain.snippet_set_id
            WHERE sales_channel_id = :saleChannelId
        SQL;
        $isoCodeResult = $this->connection->fetchOne($getIsoCodeSql, ['saleChannelId' => Uuid::fromHexToBytes($saleChannelId)]);

        static::assertEquals($isoCodeExpected, $isoCodeResult);

        $commandTester->assertCommandIsSuccessful();
    }

    public static function dataProviderTestExecuteCommandSuccess(): \Generator
    {
        yield 'should success with valid iso code' => [
            'isoCode' => 'de_DE',
            'isoCodeExpected' => 'de-DE',
        ];

        yield 'should success with invalid iso code' => [
            'isoCode' => 'xy-XY',
            'isoCodeExpected' => 'en-GB',
        ];
    }
}
