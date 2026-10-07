<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextValueResolver;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelContextValueResolver::class)]
class SalesChannelContextValueResolverTest extends TestCase
{
    public function testIgnoresArgumentsOfOtherTypes(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => Generator::generateSalesChannelContext(),
        ]);

        $resolved = (new SalesChannelContextValueResolver())->resolve($request, self::argument(Context::class));

        static::assertSame([], iterator_to_array($resolved));
    }

    /**
     * @param array<string, SalesChannelContext> $attributes
     */
    #[DataProvider('salesChannelContextProvider')]
    public function testResolvesTheSalesChannelContext(array $attributes, SalesChannelContext $expected): void
    {
        $resolved = (new SalesChannelContextValueResolver())->resolve(
            new Request(attributes: $attributes),
            self::argument(SalesChannelContext::class),
        );

        static::assertSame([$expected], iterator_to_array($resolved));
    }

    public static function salesChannelContextProvider(): \Generator
    {
        $sessionContext = Generator::generateSalesChannelContext();
        $orderContext = Generator::generateSalesChannelContext(token: 'order-token');

        yield 'order-based context of an opted-in route wins over the session context' => [
            [
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $sessionContext,
                PlatformRequest::ATTRIBUTE_EFFECTIVE_SALES_CHANNEL_CONTEXT_OBJECT => $orderContext,
            ],
            $orderContext,
        ];

        yield 'session context is used without an order-based context' => [
            [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $sessionContext],
            $sessionContext,
        ];
    }

    private static function argument(string $type): ArgumentMetadata
    {
        return new ArgumentMetadata('context', $type, isVariadic: false, hasDefaultValue: false, defaultValue: null);
    }
}
