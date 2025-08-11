<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\SalesChannelsReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Shopware\Storefront\Framework\SystemCheck\Util\StorefrontHealthCheckResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SalesChannelsReadinessCheck::class)]
class SalesChannelsReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testWhereAllChannelsAreReturningHealthy(): void
    {
        $this->createSalesChannels();
        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testWhereOneChannelIsReturningHealthyWithMocks(): void
    {
        $this->createSalesChannels();

        $util = $this->createUtilMock();
        $util->expects($this->exactly(2))
            ->method('handleRequest')
            ->willReturnOnConsecutiveCalls(
                StorefrontHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_OK,
                    1.23,
                ),
                StorefrontHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_BAD_REQUEST,
                    1.23,
                ),
            );

        $check = $this->createCheck($util);
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame(Status::ERROR, $result->status);
    }

    public function testWhenAllAreReturningErrorWithMocks(): void
    {
        $this->createSalesChannels();

        $util = $this->createUtilMock();
        $util->expects($this->exactly(2))
            ->method('handleRequest')
            ->willReturnOnConsecutiveCalls(
                StorefrontHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_BAD_REQUEST,
                    1.23,
                ),
                StorefrontHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_BAD_REQUEST,
                    1.23,
                ),
            );

        $check = $this->createCheck($util);
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame(Status::FAILURE, $result->status);
    }

    public function testTrustedHostsAreTheSameBeforeAndAfterCheck(): void
    {
        // empty test state, if this assertion fails, some other test is leaking.
        static::assertEmpty(Request::getTrustedHosts());
        Request::setTrustedHosts(['foo.bar', 'test.com']);
        $trustedHostsBefore = Request::getTrustedHosts();
        $check = $this->createCheck();
        $check->run();

        static::assertSame($trustedHostsBefore, Request::getTrustedHosts());
        // reset the trusted hosts to avoid leaking state
        Request::setTrustedHosts([]);
    }

    private function createCheck((SalesChannelDomainUtil&MockObject)|null $util = null): SalesChannelsReadinessCheck
    {
        return new SalesChannelsReadinessCheck(
            $util ?? static::getContainer()->get(SalesChannelDomainUtil::class),
            static::getContainer()->get(SalesChannelDomainProvider::class)
        );
    }

    private function createSalesChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM `sales_channel_domain`');
        $ids = new IdsCollection();
        $this->createSalesChannel([
            'id' => $ids->create('sales-channel-1'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $this->createSalesChannel([
            'id' => $ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://shop.test',
                ],
            ],
        ]);
    }

    private function createUtilMock(): SalesChannelDomainUtil&MockObject
    {
        $util = $this->createMock(SalesChannelDomainUtil::class);
        $this->initUtilMock($util);

        return $util;
    }

    private function initUtilMock(SalesChannelDomainUtil&MockObject $util): void
    {
        $util->method('runAsSalesChannelRequest')
            ->willReturnCallback(function (callable $callback): mixed {
                return $callback();
            });

        $util->method('runWhileTrustingAllHosts')
            ->willReturnCallback(function (callable $callback): mixed {
                return $callback();
            });

        $util->method('generateDomainUrl')->willReturnCallback(function ($domain, $routeName) {
            return $domain . $routeName;
        });
    }
}
