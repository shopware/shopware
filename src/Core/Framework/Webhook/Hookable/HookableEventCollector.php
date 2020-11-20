<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal only for use by the app-system
 */
class HookableEventCollector
{
    public const HOOKABLE_ENTITIES = [
        ProductDefinition::ENTITY_NAME,
        ProductPriceDefinition::ENTITY_NAME,
        CategoryDefinition::ENTITY_NAME,
        SalesChannelDefinition::ENTITY_NAME,
        CustomerDefinition::ENTITY_NAME,
        CustomerAddressDefinition::ENTITY_NAME,
    ];

    private const PRIVILEGES = 'privileges';

    /**
     * @var BusinessEventCollector
     */
    private $businessEventCollector;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var string[][][]
     */
    private $hookableEventNamesWithPrivileges = [];

    public function __construct(
        BusinessEventCollector $businessEventCollector,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->businessEventCollector = $businessEventCollector;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function getHookableEventNamesWithPrivileges(Context $context): array
    {
        if (!$this->hookableEventNamesWithPrivileges) {
            $this->hookableEventNamesWithPrivileges = $this->getEventNamesWithPrivileges($context);
        }

        return $this->hookableEventNamesWithPrivileges;
    }

    private function getEventNamesWithPrivileges(Context $context): array
    {
        return array_merge(
            $this->getEntityWrittenEventNamesWithPrivileges(),
            $this->getBusinessEventNamesWithPrivileges($context),
            $this->getHookableEventNames()
        );
    }

    private function getHookableEventNames(): array
    {
        return array_reduce(array_values(
            array_map(static function ($hookableEvent) {
                return [$hookableEvent => [self::PRIVILEGES => []]];
            }, Hookable::HOOKABLE_EVENTS)
        ), 'array_merge', []);
    }

    private function getBusinessEventNamesWithPrivileges(Context $context): array
    {
        $response = $this->businessEventCollector->collect($context);

        return array_map(function (BusinessEventDefinition $businessEventDefinition) {
            $privileges = $this->getPrivilegesFromBusinessEventDefinition($businessEventDefinition);

            return [
                self::PRIVILEGES => $privileges,
            ];
        }, $response->getElements());
    }

    private function getPrivilegesFromBusinessEventDefinition(BusinessEventDefinition $businessEventDefinition): array
    {
        $privileges = [];
        foreach ($businessEventDefinition->getData() as $data) {
            if ($data['type'] !== 'entity') {
                continue;
            }

            $entityName = $this->definitionRegistry->get($data['entityClass'])->getEntityName();
            $privileges[] = $entityName . ':' . AclRoleDefinition::PRIVILEGE_READ;
        }

        return $privileges;
    }

    private function getEntityWrittenEventNamesWithPrivileges(): array
    {
        $entityWrittenEventNames = [];
        foreach (self::HOOKABLE_ENTITIES as $entity) {
            $privileges = [
                self::PRIVILEGES => [$entity . ':' . AclRoleDefinition::PRIVILEGE_READ],
            ];

            $entityWrittenEventNames[$entity . '.written'] = $privileges;
            $entityWrittenEventNames[$entity . '.deleted'] = $privileges;
        }

        return $entityWrittenEventNames;
    }
}
