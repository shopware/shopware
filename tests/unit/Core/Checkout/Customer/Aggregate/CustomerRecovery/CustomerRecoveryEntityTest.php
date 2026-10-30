<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Aggregate\CustomerRecovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerRecoveryEntity::class)]
class CustomerRecoveryEntityTest extends TestCase
{
    public function testAccessorsRoundTrip(): void
    {
        $customer = new CustomerEntity();

        $recovery = new CustomerRecoveryEntity();
        $recovery->setCustomerId('customer-id');
        $recovery->setCustomer($customer);

        static::assertSame('customer-id', $recovery->getCustomerId());
        static::assertSame($customer, $recovery->getCustomer());
    }

    public function testHashIsReadableOutsideTwig(): void
    {
        $recovery = $this->createRecoveryWithInternalHash();
        $recovery->setHash('hash-value');

        static::assertSame('hash-value', $recovery->getHash());
    }

    public function testHashIsNotReadableInTwig(): void
    {
        $recovery = $this->createRecoveryWithInternalHash();
        $recovery->setHash('hash-value');

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('hash', CustomerRecoveryEntity::class));

        FieldVisibility::$isInTwigRenderingContext = true;

        try {
            $recovery->getHash();
        } finally {
            FieldVisibility::$isInTwigRenderingContext = false;
        }
    }

    private function createRecoveryWithInternalHash(): CustomerRecoveryEntity
    {
        $recovery = new CustomerRecoveryEntity();
        $recovery->internalSetEntityData(CustomerRecoveryDefinition::ENTITY_NAME, new FieldVisibility(['hash']));

        return $recovery;
    }
}
