<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('storefront')]
class AppLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly EntityRepository $appRepository
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
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
