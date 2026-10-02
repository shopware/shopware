<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\DeleteAddressRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\DeleteAddressRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeleteAddressRoute::class)]
class DeleteAddressRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $addressId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('delete-address-route.delete.pre', static function (DeleteAddressRouteExtension $extension) use ($addressId, $context, $customer, $response): void {
            static::assertSame(['addressId' => $addressId, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new DeleteAddressRoute(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($addressId, $context, $customer));
    }
}
