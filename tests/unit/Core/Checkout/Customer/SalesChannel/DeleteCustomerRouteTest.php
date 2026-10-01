<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\DeleteCustomerRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeleteCustomerRoute::class)]
class DeleteCustomerRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('delete-customer-route.delete.pre', static function (DeleteCustomerRouteExtension $extension) use ($context, $customer, $response): void {
            static::assertSame(['context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new DeleteCustomerRoute(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($context, $customer));
    }
}
