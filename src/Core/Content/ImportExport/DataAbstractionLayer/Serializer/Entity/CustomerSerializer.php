<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class CustomerSerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @var array<string, string|null>
     */
    private array $cacheCustomerGroups = [];

    /**
     * @var array<string, string|null>
     */
    private array $cachePaymentMethods = [];

    /**
     * @var array<string, string|null>
     */
    private array $cacheSalesChannels = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $salesChannelRepository
    ) {
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        $context = Context::createDefaultContext();

        if (!isset($deserialized['groupId']) && isset($entity['group'])) {
            $name = $entity['group']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['group']['id'] ?? $this->getCustomerGroupId($name, $context);

            if ($id) {
                $deserialized['groupId'] = $id;
                $deserialized['group']['id'] = $id;
            }
        }

        if (!isset($deserialized['defaultPaymentMethodId']) && isset($entity['defaultPaymentMethod'])) {
            $name = $entity['defaultPaymentMethod']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['defaultPaymentMethod']['id'] ?? $this->getDefaultPaymentMethodId($name, $context);

            if ($id) {
                $deserialized['defaultPaymentMethodId'] = $id;
                $deserialized['defaultPaymentMethod']['id'] = $id;
            }
        }

        if (!isset($deserialized['salesChannelId']) && isset($entity['salesChannel'])) {
            $name = $entity['salesChannel']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['salesChannel']['id'] ?? $this->getSalesChannelId($name, $context);

            if ($id) {
                $deserialized['salesChannelId'] = $id;
                $deserialized['salesChannel']['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === CustomerDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheCustomerGroups = [];
        $this->cachePaymentMethods = [];
        $this->cacheSalesChannels = [];
    }

    private function getCustomerGroupId(?string $name, Context $context): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->cacheCustomerGroups)) {
            return $this->cacheCustomerGroups[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $this->cacheCustomerGroups[$name] = $this->customerGroupRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cacheCustomerGroups[$name];
    }

    private function getDefaultPaymentMethodId(?string $name, Context $context): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->cachePaymentMethods)) {
            return $this->cachePaymentMethods[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        $this->cachePaymentMethods[$name] = $this->paymentMethodRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cachePaymentMethods[$name];
    }

    private function getSalesChannelId(?string $name, Context $context): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->cacheSalesChannels)) {
            return $this->cacheSalesChannels[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        $this->cacheSalesChannels[$name] = $this->salesChannelRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cacheSalesChannels[$name];
    }
}
