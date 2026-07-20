<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Services\DecodedPurchaseStruct;
use Shopware\Core\Framework\Validation\ValidatorFactory;

/**
 * @internal
 */
#[CoversClass(DecodedPurchaseStruct::class)]
#[Package('checkout')]
class DecodedPurchaseStructTest extends TestCase
{
    public function testWithAdditionalFieldsAllowed(): void
    {
        $element = [
            'identifier' => 'SwagTest',
            'nextBookingDate' => '2025-12-12',
            'quantity' => 1,
            'sub' => 'sub',
            'test' => 'test',
            'addition' => [
                'test' => 'test',
            ],
        ];

        $dto = ValidatorFactory::create($element, DecodedPurchaseStruct::class, true);

        static::assertInstanceOf(DecodedPurchaseStruct::class, $dto);
    }

    public function testWithAdditionalFieldsNotAllowed(): void
    {
        $element = [
            'identifier' => 'SwagTest',
            'nextBookingDate' => '2025-12-12',
            'quantity' => 1,
            'sub' => 'sub',
            'test' => 'test',
        ];

        static::expectException(FrameworkException::class);

        ValidatorFactory::create($element, DecodedPurchaseStruct::class);
    }
}
