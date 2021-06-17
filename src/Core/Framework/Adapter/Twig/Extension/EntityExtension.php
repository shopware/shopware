<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EntityExtension extends AbstractExtension
{
    private DefinitionInstanceRegistry $definitionRegistry;

    private SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry;

    public function __construct(
        SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->salesChannelDefinitionRegistry = $salesChannelDefinitionRegistry;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('entities', [$this, 'searchEntities'], ['needs_context' => true]),
        ];
    }

    public function searchEntities(array $twigContext, string $entityName, array $ids, array $associations = []): EntityCollection
    {
        if (!\array_key_exists('context', $twigContext)) {
            throw new \InvalidArgumentException('Error while processing Twig entities. No context given.');
        }

        $repository = $this->definitionRegistry->getRepository($entityName);
        $collectionClass = $repository->getDefinition()->getCollectionClass();

        if (empty($ids)) {
            return new $collectionClass();
        }

        $criteria = new Criteria($ids);
        $criteria->addAssociations($associations);

        $context = $twigContext['context'];

        if ($this->salesChannelDefinitionRegistry->has($entityName) && $context instanceof SalesChannelContext) {
            $salesChannelRepository = $this->salesChannelDefinitionRegistry->getSalesChannelRepository($entityName);

            return $salesChannelRepository->search($criteria, $context)->getEntities();
        }

        if ($context instanceof Context) {
            return $repository->search($criteria, $context)->getEntities();
        }

        throw new \InvalidArgumentException('Error while processing Twig entities. Context given is invalid.');
    }
}
