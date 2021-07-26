<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class CustomerSerializer extends EntitySerializer
{
    private EntityRepositoryInterface $customerGroupRepository;

    private EntityRepositoryInterface $paymentMethodRepository;

    private EntityRepositoryInterface $salesChannelRepository;

    /**
     * @var string[]|null[]
     */
    private array $customerGroups = [];

    /**
     * @var string[]|null[]
     */
    private array $paymentMethods = [];

    /**
     * @var string[]|null[]
     */
    private array $salesChannels = [];

    public function __construct(
        EntityRepositoryInterface $customerGroupRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->customerGroupRepository = $customerGroupRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * @param array|\Traversable $entity
     *
     * @return array|\Traversable
     */
    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        if (!isset($deserialized['groupId']) && isset($entity['group'])) {
            $name = $entity['group']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['group']['id'] ?? $this->getCustomerGroupId($name);

            if ($id) {
                $deserialized['groupId'] = $id;
                $deserialized['group']['id'] = $id;
            }
        }

        if (!isset($deserialized['defaultPaymentMethodId']) && isset($entity['defaultPaymentMethod'])) {
            $name = $entity['defaultPaymentMethod']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['defaultPaymentMethod']['id'] ?? $this->getDefaultPaymentMethodId($name);

            if ($id) {
                $deserialized['defaultPaymentMethodId'] = $id;
                $deserialized['defaultPaymentMethod']['id'] = $id;
            }
        }

        if (!isset($deserialized['salesChannelId']) && isset($entity['salesChannel'])) {
            $name = $entity['salesChannel']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['salesChannel']['id'] ?? $this->getSalesChannelId($name);

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

    private function getCustomerGroupId(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->customerGroups)) {
            return $this->customerGroups[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $group = $this->customerGroupRepository->search($criteria, Context::createDefaultContext())->first();

        $this->customerGroups[$name] = null;
        if ($group instanceof CustomerGroupEntity) {
            $this->customerGroups[$name] = $group->getId();
        }

        return $this->customerGroups[$name];
    }

    private function getDefaultPaymentMethodId(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->paymentMethods)) {
            return $this->paymentMethods[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();

        $this->paymentMethods[$name] = null;
        if ($paymentMethod instanceof PaymentMethodEntity) {
            $this->paymentMethods[$name] = $paymentMethod->getId();
        }

        return $this->paymentMethods[$name];
    }

    private function getSalesChannelId(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->salesChannels)) {
            return $this->salesChannels[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $salesChannel = $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->first();

        $this->salesChannels[$name] = null;
        if ($salesChannel instanceof SalesChannelEntity) {
            $this->salesChannels[$name] = $salesChannel->getId();
        }

        return $this->salesChannels[$name];
    }
}
