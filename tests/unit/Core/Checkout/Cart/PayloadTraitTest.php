<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\PayloadTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversTrait(PayloadTrait::class)]
class PayloadTraitTest extends TestCase
{
    public function testPayloadIsEmptyByDefault(): void
    {
        $struct = new PayloadTraitUser();

        static::assertSame([], $struct->getPayload());
        static::assertFalse($struct->hasPayloadValue('group'));
        static::assertNull($struct->getPayloadValue('group'));
    }

    public function testSetPayloadKeepsTheKeysOfEarlierCalls(): void
    {
        $struct = new PayloadTraitUser();
        $struct->setPayload(['group' => 'now', 'warehouseId' => 'wh-1']);
        $struct->setPayload(['warehouseId' => 'wh-2', 'carrierContract' => 'c-1']);

        static::assertSame(['group' => 'now', 'warehouseId' => 'wh-2', 'carrierContract' => 'c-1'], $struct->getPayload());
    }

    public function testPayloadValuesCanBeSetReadAndRemoved(): void
    {
        $struct = (new PayloadTraitUser())
            ->setPayloadValue('group', 'now')
            ->setPayloadValue('warehouseId', 'wh-1');

        static::assertTrue($struct->hasPayloadValue('warehouseId'));
        static::assertSame('wh-1', $struct->getPayloadValue('warehouseId'));

        $struct->removePayloadValue('group');

        static::assertSame(['warehouseId' => 'wh-1'], $struct->getPayload());
    }

    public function testRemovingAnUnknownPayloadKeyThrows(): void
    {
        $this->expectExceptionObject(CartException::structPayloadKeyNotFound('unknown', 'payload_trait_user'));

        (new PayloadTraitUser())->removePayloadValue('unknown');
    }

    public function testProtectedPayloadValuesStayOutOfTheUnprotectedPayload(): void
    {
        $struct = (new PayloadTraitUser())
            ->setPayload(['group' => 'now', 'warehouseId' => 'wh-1'], ['warehouseId' => true])
            ->setPayloadValue('carrierContract', 'c-1', protected: true)
            ->setPayloadValue('warehouseId', 'wh-2');

        static::assertSame(['group' => 'now', 'warehouseId' => 'wh-2', 'carrierContract' => 'c-1'], $struct->getPayload());
        static::assertSame(['group' => 'now'], $struct->getUnprotectedPayload());
    }

    public function testPayloadProtectionCanBeLiftedAndEndsWithTheValue(): void
    {
        $struct = (new PayloadTraitUser())
            ->setPayloadValue('warehouseId', 'wh-1', protected: true)
            ->setPayloadValue('carrierContract', 'c-1', protected: true)
            ->setPayloadValue('warehouseId', 'wh-1', protected: false);

        $struct->removePayloadValue('carrierContract');
        $struct->setPayloadValue('carrierContract', 'c-2');

        static::assertSame(['warehouseId' => 'wh-1', 'carrierContract' => 'c-2'], $struct->getUnprotectedPayload());
    }
}

/**
 * @internal
 */
class PayloadTraitUser extends Struct
{
    use PayloadTrait {
        getUnprotectedPayload as public;
    }

    public function getApiAlias(): string
    {
        return 'payload_trait_user';
    }
}
