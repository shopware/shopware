<?php declare(strict_types=1);

namespace Shopware\Api\Test\Order\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Order\Definition\OrderDefinition;

class OrderDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = OrderDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'customerId', 'stateId', 'paymentMethodId', 'currencyId', 'shopId', 'billingAddressId', 'date', 'amountTotal', 'positionPrice', 'shippingTotal', 'isNet', 'isTaxFree', 'context', 'payload'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = OrderDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['deliveries', 'lineItems'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = OrderDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
