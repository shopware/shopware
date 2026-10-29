<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Store\Struct\FrwState;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\Subscriber\FirstRunWizardSubscriber;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FirstRunWizardSubscriber::class)]
class FirstRunWizardSubscriberTest extends TestCase
{
    private ThemeService&MockObject $themeService;

    private string $themeId;

    protected function setUp(): void
    {
        $this->themeService = $this->createMock(ThemeService::class);
        $this->themeId = Uuid::randomHex();
    }

    public function testFrwFinishedAssignsTheDefaultThemeToEveryStorefrontSalesChannel(): void
    {
        $salesChannelIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $themeSalesChannelRepository = new StaticEntityRepository([]);
        $subscriber = $this->createSubscriber($salesChannelIds, $themeSalesChannelRepository);

        $this->themeService
            ->expects($this->exactly(\count($salesChannelIds)))
            ->method('compileTheme');

        $subscriber->frwFinished($this->createEvent());

        static::assertSame(
            array_map(fn (string $id): array => [['themeId' => $this->themeId, 'salesChannelId' => $id]], $salesChannelIds),
            $themeSalesChannelRepository->upserts
        );
    }

    public function testFrwFinishedDoesNothingWhileTheWizardIsStillOpen(): void
    {
        $themeSalesChannelRepository = new StaticEntityRepository([]);
        $subscriber = $this->createSubscriber([Uuid::randomHex()], $themeSalesChannelRepository);

        $this->themeService->expects($this->never())->method('compileTheme');

        $subscriber->frwFinished(new FirstRunWizardFinishedEvent(
            FrwState::openState(),
            FrwState::openState(),
            Context::createDefaultContext()
        ));

        static::assertSame([], $themeSalesChannelRepository->upserts);
    }

    /**
     * @param list<string> $salesChannelIds
     * @param StaticEntityRepository<EntityCollection<Entity>> $themeSalesChannelRepository
     */
    private function createSubscriber(array $salesChannelIds, StaticEntityRepository $themeSalesChannelRepository): FirstRunWizardSubscriber
    {
        $theme = new ThemeEntity();
        $theme->setUniqueIdentifier($this->themeId);
        $theme->setId($this->themeId);

        /** @var StaticEntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = new StaticEntityRepository([new ThemeCollection([$theme])]);

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([$salesChannelIds]);

        return new FirstRunWizardSubscriber(
            $this->themeService,
            $this->createMock(ThemeLifecycleService::class),
            $themeRepository,
            $themeSalesChannelRepository,
            $salesChannelRepository
        );
    }

    private function createEvent(): FirstRunWizardFinishedEvent
    {
        return new FirstRunWizardFinishedEvent(
            FrwState::completedState(),
            FrwState::openState(),
            Context::createDefaultContext()
        );
    }
}
