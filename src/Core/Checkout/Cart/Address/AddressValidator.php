<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address;

use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddressValidator implements CartValidatorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var bool[]
     */
    private $available = [];

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $country = $context->getShippingLocation()->getCountry();

        if (!$country->getActive()) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$country->getShippingAvailable()) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$this->isSalesChannelCountry($country->getId(), $context)) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }
    }

    private function isSalesChannelCountry(string $countryId, SalesChannelContext $context): bool
    {
        if (isset($this->available[$countryId])) {
            return $this->available[$countryId];
        }

        $criteria = new Criteria([$countryId]);
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));

        $available = $this->repository->searchIds($criteria, $context->getContext());

        return $this->available[$countryId] = $available->has($countryId);
    }
}
