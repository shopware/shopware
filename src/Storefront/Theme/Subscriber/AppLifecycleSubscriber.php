<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AppLifecycleSubscriber implements EventSubscriberInterface
{
    private ThemeLifecycleService $themeLifecycleService;

    private EntityRepository $appRepository;

    /**
     * @internal
     */
    public function __construct(ThemeLifecycleService $themeLifecycleService, EntityRepository $appRepository)
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
