<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 * @package storefront
 */
#[Package('storefront')]
class AppLifecycleSubscriber implements EventSubscriberInterface
{
    private ThemeLifecycleService $themeLifecycleService;

    private EntityRepositoryInterface $appRepository;

    /**
     * @internal
     */
    public function __construct(ThemeLifecycleService $themeLifecycleService, EntityRepositoryInterface $appRepository)
    {
        $this->themeLifecycleService = $themeLifecycleService;
        $this->appRepository = $appRepository;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            AppDeletedEvent::class => 'onAppDeleted',
        ];
    }

    public function onAppDeleted(AppDeletedEvent $event): void
    {
        if ($event->keepUserData()) {
            return;
        }

        $app = $this->appRepository->search((new Criteria([$event->getAppId()])), $event->getContext())->first();

        if ($app === null) {
            return;
        }

        $this->themeLifecycleService->removeTheme($app->getName(), $event->getContext());
    }
}
