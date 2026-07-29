<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<ProductProvider>
 */
#[Package('after-sales')]
#[CoversClass(ProductProvider::class)]
class ProductProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): ProductProvider {
        return new ProductProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
