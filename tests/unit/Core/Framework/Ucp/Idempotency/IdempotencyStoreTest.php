<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Idempotency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Idempotency\IdempotencyStore;

/**
 * @internal
 */
#[CoversClass(IdempotencyStore::class)]
class IdempotencyStoreTest extends TestCase
{
    public function testFingerprintIncludesRouteName(): void
    {
        $body = '{"line_items":[]}';

        $cartCreate = IdempotencyStore::computeFingerprint(
            'ucp.cart.create',
            'POST',
            '/ucp/v1/carts',
            '',
            $body
        );
        $checkoutCreate = IdempotencyStore::computeFingerprint(
            'ucp.checkout.create',
            'POST',
            '/ucp/v1/carts',
            '',
            $body
        );

        static::assertNotSame($cartCreate, $checkoutCreate);
    }

    public function testFingerprintNormalisesQueryOrder(): void
    {
        $a = IdempotencyStore::computeFingerprint('r', 'POST', '/p', 'b=2&a=1', '{}');
        $b = IdempotencyStore::computeFingerprint('r', 'POST', '/p', 'a=1&b=2', '{}');

        static::assertSame($a, $b);
    }
}
