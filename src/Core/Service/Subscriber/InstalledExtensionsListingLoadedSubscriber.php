<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Event\InstalledExtensionsListingLoadedEvent;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class InstalledExtensionsListingLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(private readonly EntityRepository $appRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InstalledExtensionsListingLoadedEvent::class => 'removeAppsWithService',
        ];
    }

    /**
     * Remove apps from the listing which have an installed service equivalent
     */
    public function removeAppsWithService(InstalledExtensionsListingLoadedEvent $event): void
    {
        $existingServices = $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('selfManaged', true)),
            $event->context
        )->getEntities();

        $names = array_values($existingServices->map(fn (AppEntity $app) => $app->getName()));

        $event->extensionCollection = $event->extensionCollection->filter(
            fn (ExtensionStruct $ext) => !\in_array($ext->getName(), $names, true)
        );
    }
}
