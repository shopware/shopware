<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\ChangeLanguageRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeLanguageRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ChangeLanguageRoute::class)]
class ChangeLanguageRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $requestDataBag = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('change-language-route.change.pre', static function (ChangeLanguageRouteExtension $extension) use ($requestDataBag, $context, $customer, $response): void {
            static::assertSame(['requestDataBag' => $requestDataBag, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ChangeLanguageRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($requestDataBag, $context, $customer));
    }
}
