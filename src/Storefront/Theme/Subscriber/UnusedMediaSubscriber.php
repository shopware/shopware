<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('storefront')]
class UnusedMediaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $themeRepository,
        private readonly ThemeService $themeService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UnusedMediaSearchEvent::class => 'removeUsedMedia',
        ];
    }

    public function removeUsedMedia(UnusedMediaSearchEvent $event): void
    {
        $context = Context::createDefaultContext();
        /** @var array<string> $allThemeIds */
        $allThemeIds = $this->themeRepository->searchIds(new Criteria(), $context)->getIds();

        $mediaIds = [];
        foreach ($allThemeIds as $themeId) {
            $config = $this->themeService->getThemeConfiguration($themeId, false, $context);

            foreach ($config['fields'] ?? [] as $data) {
                if ($data['type'] === 'media' && $data['value'] && Uuid::isValid($data['value'])) {
                    $mediaIds[] = $data['value'];
                }
            }
        }

        $event->markAsUsed(array_unique($mediaIds));
    }
}
