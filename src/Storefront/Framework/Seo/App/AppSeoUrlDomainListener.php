<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlDomainListener implements EventSubscriberInterface
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelDomainDefinition::ENTITY_NAME . '.written' => 'syncStaticSeoUrls',
        ];
    }

    public function syncStaticSeoUrls(): void
    {
        $this->messageBus->dispatch(new AppSeoUrlSyncMessage());
    }
}
