<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderInterface;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
class EntityLoader implements ContentDataLoaderInterface
{
    public function __construct(
        private readonly SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public static function getRequirementType(): string
    {
        return 'entity';
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context
    ): SalesChannelEntity|Entity|null {
        $config = $requirement->config;

        if (!$config instanceof EntityLoaderConfig) {
            return null;
        }

        $propertyName = $config->property ?? $config->entity;
        $entityId = $element->getProperty($propertyName);

        if ($entityId === null) {
            return null;
        }

        if (!\is_string($entityId)) {
            return null;
        }

        return $this->loadEntity($config->entity, $entityId, $config->associations, $context);
    }

    /**
     * @param list<string> $associations
     */
    private function loadEntity(
        string $entityName,
        string $entityId,
        array $associations,
        SalesChannelContext $context
    ): SalesChannelEntity|Entity|null {
        $criteria = new Criteria([$entityId]);

        foreach ($associations as $association) {
            if (\is_string($association)) {
                $criteria->addAssociation($association);
            }
        }

        try {
            $salesChannelRepository = $this->salesChannelDefinitionRegistry->getSalesChannelRepository($entityName);
            $result = $salesChannelRepository->search($criteria, $context);
        } catch (SalesChannelRepositoryNotFoundException) {
            $repository = $this->definitionRegistry->getRepository($entityName);
            $result = $repository->search($criteria, $context->getContext());
        }

        return $result->first();
    }
}
