<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRequestDataBagResolver;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[CoversClass(StorefrontRequestDataBagResolver::class)]
class StorefrontRequestDataBagResolverTest extends TestCase
{
    public function testResolvesTrimmedStorefrontRequestDataBag(): void
    {
        $request = new Request(
            request: [
                'firstName' => '  Max  ',
                'billingAddress' => [
                    'street' => '  Ebbinghoff 10  ',
                    'zipcode' => '  48624  ',
                    'city' => '  Schoeppingen  ',
                ],
                'lineItems' => [
                    [
                        'referencedId' => '  product-id  ',
                    ],
                ],
                'password' => '  password  ',
                'passwordConfirmation' => '  password  ',
            ],
            attributes: [
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
            ],
        );

        $resolved = iterator_to_array((new StorefrontRequestDataBagResolver())->resolve($request, $this->requestDataBagArgument()));

        static::assertCount(1, $resolved);
        static::assertInstanceOf(RequestDataBag::class, $resolved[0]);
        static::assertSame([
            'firstName' => 'Max',
            'billingAddress' => [
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schoeppingen',
            ],
            'lineItems' => [
                [
                    'referencedId' => 'product-id',
                ],
            ],
            'password' => '  password  ',
            'passwordConfirmation' => '  password  ',
        ], $resolved[0]->all());
    }

    public function testDoesNotResolveNonStorefrontRequest(): void
    {
        $request = new Request(
            request: [
                'firstName' => '  Max  ',
            ],
        );

        $resolved = iterator_to_array((new StorefrontRequestDataBagResolver())->resolve($request, $this->requestDataBagArgument()));

        static::assertSame([], $resolved);
    }

    public function testDoesNotResolveDifferentArgumentType(): void
    {
        $request = new Request(
            request: [
                'firstName' => '  Max  ',
            ],
            attributes: [
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
            ],
        );

        $resolved = iterator_to_array((new StorefrontRequestDataBagResolver())->resolve($request, new ArgumentMetadata('data', 'string', false, false, null)));

        static::assertSame([], $resolved);
    }

    private function requestDataBagArgument(): ArgumentMetadata
    {
        return new ArgumentMetadata('data', RequestDataBag::class, false, false, null);
    }
}
