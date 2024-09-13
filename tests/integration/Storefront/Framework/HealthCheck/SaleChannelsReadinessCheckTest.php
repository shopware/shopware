<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Kernel;
use Shopware\Storefront\Framework\SystemCheck\SaleChannelsReadinessCheck;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SaleChannelsReadinessCheck::class)]
class SaleChannelsReadinessCheckTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Kernel&MockObject $kernel;

    private SaleChannelsReadinessCheck $check;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->kernel = $this->createMock(Kernel::class);
        $this->check = new SaleChannelsReadinessCheck(
            $this->kernel,
            $this->getContainer()->get('router'),
            $this->getContainer()->get(RequestTransformerInterface::class),
            $this->connection,
            $this->getContainer()->get('request_stack')
        );
    }

    public function testWhereAllChannelsAreReturningHealthy(): void
    {
        $this->connection->executeStatement('DELETE FROM `sales_channel_domain`');
        $this->createSalesChannel([
            'id' => (new TestDataCollection())->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://test.to',
                ],
            ],
        ]);

        $this->kernel->expects(static::exactly(1))
            ->method('handle')
            ->willReturn(new Response());

        $result = $this->check->run();
        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testWhereOneChannelIsReturningHealthy(): void
    {
        $this->connection->executeStatement('DELETE FROM `sales_channel_domain`');
        $ids = new TestDataCollection();
        $this->createSalesChannel([
            'id' => $ids->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://test.to',
                ],
            ],
        ]);

        $this->createSalesChannel([
            'id' => $ids->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://foo.to',
                ],
            ],
        ]);

        $this->kernel->expects(static::exactly(2))
            ->method('handle')
            ->willReturnOnConsecutiveCalls(
                new Response(),
                new Response(null, Response::HTTP_BAD_REQUEST)
            );

        $result = $this->check->run();
        static::assertFalse($result->healthy);
        static::assertSame(Status::ERROR, $result->status);
    }

    public function testWhenAllAreReturningError(): void
    {
        $this->connection->executeStatement('DELETE FROM `sales_channel_domain`');
        $ids = new TestDataCollection();
        $this->createSalesChannel([
            'id' => $ids->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://test.to',
                ],
            ],
        ]);

        $this->createSalesChannel([
            'id' => $ids->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://foo.to',
                ],
            ],
        ]);

        $this->kernel->expects(static::exactly(2))
            ->method('handle')
            ->willReturnOnConsecutiveCalls(
                new Response(null, Response::HTTP_BAD_REQUEST),
                new Response(null, Response::HTTP_BAD_REQUEST)
            );

        $result = $this->check->run();
        static::assertFalse($result->healthy);
        static::assertSame(Status::FAILURE, $result->status);
    }
}
