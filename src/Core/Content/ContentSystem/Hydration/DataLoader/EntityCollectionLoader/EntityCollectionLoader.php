<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\UnicodeString;

/**
 * @internal
 */
#[Package('discovery')]
class EntityCollectionLoader extends AbstractContentDataLoader
{
    public function __construct(
        private readonly SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public function getDecorated(): AbstractContentDataLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public static function getRequirementType(): string
    {
        return 'entity_collection';
    }

    /**
     * @return list<covariant Entity>|null
     */
    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ?array {
        $config = $requirement->config;

        if (!$config instanceof EntityLoaderConfig) {
            return null;
        }

        $propertyName = $config->property ?? $config->entity . 'Ids';
        $entityIds = $element->getProperty($propertyName);

        if ($entityIds === null) {
            return [];
        }

        if (!\is_array($entityIds)) {
            return null;
        }

        $entityIds = \array_filter($entityIds, static fn ($id) => \is_string($id));
        $entityIds = \array_map(static fn ($entityId) => (new UnicodeString($entityId))->lower()->toString(), $entityIds);
        $entityIds = \array_values($entityIds);

        if ($entityIds === []) {
            return null;
        }

        return $this->loadEntities($config->entity, $entityIds, $config->associations, $context);
    }

    /**
     * @param list<string> $entityIds
     * @param list<string> $associations
     *
     * @return list<covariant Entity>
     */
    private function loadEntities(
        string $entityName,
        array $entityIds,
        array $associations,
        SalesChannelContext $context
    ): array {
        $criteria = new Criteria($entityIds);

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

        return array_values($result->getEntities()->getElements());
    }
}
