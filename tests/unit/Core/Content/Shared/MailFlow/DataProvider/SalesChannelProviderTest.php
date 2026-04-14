<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<SalesChannelProvider>
 */
#[Package('after-sales')]
#[CoversClass(SalesChannelProvider::class)]
class SalesChannelProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): SalesChannelProvider {
        return new SalesChannelProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'domains',
            'mailHeaderFooter',
        ];
    }
}
