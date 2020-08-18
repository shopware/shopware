<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait CustomerAddressValidationTrait
{
    private function validateAddress(string $id, SalesChannelContext $context): void
    {
        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('customerId', $context->getCustomer()->getId()));

        if (count($this->addressRepository->searchIds($criteria, $context->getContext())->getIds())) {
            return;
        }

        throw new AddressNotFoundException($id);
    }
}
