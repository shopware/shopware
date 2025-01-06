<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Framework\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Services\DecodedPurchaseStruct;
use Shopware\Core\Framework\Validation\ValidatorFactory;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(ValidatorFactory::class)]
class ValidatorFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $data = [
            'identifier' => 'some-identifier',
            'nextBookingDate' => '2023-10-10',
            'quantity' => 10,
            'sub' => 'some-sub',
        ];

        $result = ValidatorFactory::create($data, DecodedPurchaseStruct::class);

        static::assertInstanceOf(DecodedPurchaseStruct::class, $result);
        static::assertSame('some-identifier', $result->identifier);
        static::assertSame(10, $result->quantity);
    }
}
