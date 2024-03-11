<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;

/**
 * @internal
 */
#[CoversClass(ProductExportGenerateTaskHandler::class)]
class ProductExportGenerateTaskHandlerTest extends TestCase
{
    #[DataProvider('shouldBeRunDataProvider')]
    public function testShouldBeRun(ProductExportEntity $productExportEntity, bool $expectedResult): void
    {
        $salesChannelRepositoryMock = $this->getSalesChannelRepositoryMock();
        $salesChannelContextFactoryMock = $this->getSalesChannelContextFactoryMock();
        $productExportRepositoryMock = $this->getProductExportRepositoryMock($productExportEntity);

        $messageBusMock = new CollectingMessageBus();

        $productExportGenerateTaskHandler = new ProductExportGenerateTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $salesChannelContextFactoryMock,
            $salesChannelRepositoryMock,
            $productExportRepositoryMock,
            $messageBusMock
        );

        $productExportGenerateTaskHandler->run();

        if ($expectedResult) {
            static::assertCount(1, $messageBusMock->getMessages());
        } else {
            static::assertCount(0, $messageBusMock->getMessages());
        }
    }

    public static function shouldBeRunDataProvider(): \Generator
    {
        yield 'next generation not reached' => [
            // Should not run because: next generation time not reached (time + intervall > now)
            self::prepareProductExportEntity(false, false, 45),
            false,
        ];
        yield 'already running' => [
            // Should not run because: is running is true (another export is being generated atm.)
            self::prepareProductExportEntity(true, false, 10),
            false,
        ];
        yield 'not generated before' => [
            // Should run because: has not been generated before
            self::prepareProductExportEntity(false, null, 0),
            true,
        ];
        yield 'generation is due' => [
            // Should run because: next run is due (last generated + intervall < now)
            self::prepareProductExportEntity(false, true, 10),
            true,
        ];
    }

    private static function getGeneratedAtTimestamp(?bool $generatedAtBeforeInterval): ?\DateTime
    {
        if ($generatedAtBeforeInterval === true) {
            return new \DateTime('1022-07-18 10:59:30');
        }
        if ($generatedAtBeforeInterval === false) {
            return new \DateTime('3022-07-18 10:59:30');
        }

        return null;
    }

    private static function prepareProductExportEntity(bool $isRunning, ?bool $generatedAtBeforeInterval, int $interval): ProductExportEntity
    {
        $productExportEntity = new ProductExportEntity();
        $productExportEntity->setIsRunning($isRunning);
        $productExportEntity->setGeneratedAt(self::getGeneratedAtTimestamp($generatedAtBeforeInterval));
        $productExportEntity->setInterval($interval);
        $productExportEntity->setUniqueIdentifier('TestExportEntity');
        $productExportEntity->setId('afdd4e21be6b4ad59656fb856d0375e5');

        return $productExportEntity;
    }

    private function getProductExportRepositoryMock(ProductExportEntity $productExportEntity): EntityRepository
    {
        $productEntitySearchResult = new EntitySearchResult(
            'product',
            1,
            new EntityCollection([$productExportEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $productExportRepositoryMock = $this->createMock(EntityRepository::class);
        $productExportRepositoryMock->method('search')->willReturn($productEntitySearchResult);

        return $productExportRepositoryMock;
    }

    private function getSalesChannelRepositoryMock(): EntityRepository
    {
        return new StaticEntityRepository([['8fdd4e21be6b4ad59656fb856d0375e7']]);
    }

    private function getSalesChannelContextFactoryMock(): SalesChannelContextFactory
    {
        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getContext')->willReturn(Context::createDefaultContext());

        $salesChannelContextFactoryMock = $this->createMock(SalesChannelContextFactory::class);
        $salesChannelContextFactoryMock->method('create')->willReturn($salesChannelContextMock);

        return $salesChannelContextFactoryMock;
    }
}
