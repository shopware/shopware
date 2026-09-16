<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class SalesChannelCustomerAddressDefinition extends CustomerAddressDefinition implements SalesChannelDefinitionInterface
{
    public function getEntityClass(): string
    {
        return SalesChannelCustomerAddressEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelCustomerAddressCollection::class;
    }

    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('customerId', $context->getCustomer()?->getId()));
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new BoolField('is_default_billing_address', 'isDefaultBillingAddress'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.7.0'))
        );
        $fields->add(
            (new BoolField('is_default_shipping_address', 'isDefaultShippingAddress'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.7.0'))
        );

        return $fields;
    }
}
