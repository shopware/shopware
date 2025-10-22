<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\ShopIdChangeStrategyNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ShopIdChangeStrategyNotFoundException::class)]
#[Package('framework')]
class ShopIdChangeStrategyNotFoundExceptionTest extends TestCase
{
    public function testException(): void
    {
        $e = new ShopIdChangeStrategyNotFoundException('testStrategy');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_SHOP_ID_CHANGE_STRATEGY_NOT_FOUND', $e->getErrorCode());
        static::assertSame('Shop ID change resolver with name "testStrategy" not found.', $e->getMessage());
    }
}
