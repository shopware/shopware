<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextRoute;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextRoute::class)]
class ContextRouteTest extends TestCase
{
    public function testLoadReturnsContextTokenHeader(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'test-token');

        $response = (new ContextRoute())->load($context);

        static::assertSame($context, $response->getContext());
        static::assertSame('test-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
