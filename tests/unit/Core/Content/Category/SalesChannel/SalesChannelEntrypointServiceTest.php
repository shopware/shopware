<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\SalesChannelEntrypointEvent;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelEntrypointService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SalesChannelEntrypointService::class)]
class SalesChannelEntrypointServiceTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private SalesChannelEntrypointService $entrypointService;

    private SalesChannelEntity $salesChannel;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->entrypointService = new SalesChannelEntrypointService($this->eventDispatcher);

        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setId(Uuid::randomHex());
    }

    public function testLoadsCoreEntrypoints(): void
    {
        $navigationCategoryId = Uuid::randomHex();
        $this->salesChannel->setNavigationCategoryId($navigationCategoryId);
        $serviceCategoryId = Uuid::randomHex();
        $this->salesChannel->setServiceCategoryId($serviceCategoryId);
        $footerCategoryId = Uuid::randomHex();
        $this->salesChannel->setFooterCategoryId($footerCategoryId);

        $entrypoints = $this->entrypointService->getEntrypointIds($this->salesChannel);

        static::assertContains($navigationCategoryId, $entrypoints);
        static::assertContains($serviceCategoryId, $entrypoints);
        static::assertContains($footerCategoryId, $entrypoints);
    }

    public function testLoadsCustomEntrypoints(): void
    {
        $this->salesChannel->setNavigationCategoryId(Uuid::randomHex());

        $additionalId = Uuid::randomHex();
        $this->eventDispatcher->addListener(
            SalesChannelEntrypointEvent::class,
            function (SalesChannelEntrypointEvent $event): void {
                $event->addEntrypointType('additional');
            }
        );
        $this->salesChannel->setEntrypointIds(['additional' => $additionalId]);

        $entrypoints = $this->entrypointService->getEntrypointIds($this->salesChannel);

        static::assertContains($additionalId, $entrypoints);
    }
}
