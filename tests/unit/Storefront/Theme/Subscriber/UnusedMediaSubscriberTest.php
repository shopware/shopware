<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\Subscriber\UnusedMediaSubscriber;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[CoversClass(UnusedMediaSubscriber::class)]
class UnusedMediaSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertEquals(
            [
                UnusedMediaSearchEvent::class => 'removeUsedMedia',
            ],
            UnusedMediaSubscriber::getSubscribedEvents()
        );
    }

    public function testUsedThemeMediaIdsAreRemoved(): void
    {
        $themeId1 = Uuid::randomHex();
        $themeId2 = Uuid::randomHex();

        $mediaId1 = Uuid::randomHex();
        $mediaId2 = Uuid::randomHex();
        $mediaId3 = Uuid::randomHex();
        $mediaId4 = Uuid::randomHex();
        $mediaId5 = Uuid::randomHex();

        $themeConfig1 = [
            'fields' => [
                ['type' => 'media', 'value' => $mediaId1],
            ],
        ];
        $themeConfig2 = [
            'fields' => [
                ['type' => 'media', 'value' => $mediaId2],
                ['type' => 'media', 'value' => $mediaId3],
            ],
        ];

        $themeRepository = new StaticEntityRepository([
            function (Criteria $criteria, Context $context) use ($themeId1, $themeId2) {
                return new IdSearchResult(2, [['primaryKey' => $themeId1, 'data' => []], ['primaryKey' => $themeId2, 'data' => []]], $criteria, $context);
            },
        ]);

        $themeConfigMap = [
            $themeId1 => $themeConfig1,
            $themeId2 => $themeConfig2,
        ];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects(static::exactly(2))
            ->method('getThemeConfiguration')
            ->willReturnCallback(function (string $themeId, ...$params) use ($themeConfigMap) {
                return $themeConfigMap[$themeId];
            });

        $event = new UnusedMediaSearchEvent([$mediaId1, $mediaId2, $mediaId3, $mediaId4, $mediaId5]);
        $listener = new UnusedMediaSubscriber($themeRepository, $themeService);
        $listener->removeUsedMedia($event);

        static::assertEquals([$mediaId4, $mediaId5], $event->getUnusedIds());
    }
}
