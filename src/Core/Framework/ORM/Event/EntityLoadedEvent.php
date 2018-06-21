<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;

use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;

class EntityLoadedEvent extends NestedEvent
{
    /**
     * @var EntityCollection
     */
    protected $entities;

    /**
     * @var string|EntityDefinition
     */
    protected $definition;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $name;

    public function __construct(string $definition, EntityCollection $entities, Context $context)
    {
        $this->entities = $entities;
        $this->definition = $definition;
        $this->context = $context;
        $this->name = $this->definition::getEntityName() . '.loaded';
    }

    public function getEntities()
    {
        return $this->entities;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $associations = $this->extractAssociations($this->definition, $this->entities->getElements());

        $events = [];
        foreach ($associations as $definition => $entities) {
            /** @var string|EntityDefinition $definition */
            $collection = $definition::getCollectionClass();

            $events[] = new EntityLoadedEvent($definition, new $collection($entities), $this->context);
        }

        return new NestedEventCollection($events);
    }

    protected function extractAssociations(string $definition, array $entities): array
    {
        /** @var string|EntityDefinition $definition */
        $associations = $definition::getFields()->getElements();

        $events = [];
        /** @var AssociationInterface $association */
        foreach ($associations as $association) {
            if (!$association instanceof AssociationInterface) {
                continue;
            }
            if ($association instanceof ManyToOneAssociationField) {
                /** @var Entity $entity */
                foreach ($entities as $entity) {
                    $reference = $entity->get($association->getPropertyName());

                    if ($reference) {
                        $events[$association->getReferenceClass()][] = $reference;
                    }
                }

                continue;
            }

            $referenceClass = $association->getReferenceClass();
            if ($association instanceof ManyToManyAssociationField) {
                $referenceClass = $association->getReferenceDefinition();
            }

            foreach ($entities as $entity) {
                $references = $entity->get($association->getPropertyName());

                if (empty($references)) {
                    continue;
                }

                foreach ($references as $reference) {
                    $events[$referenceClass][] = $reference;
                }
            }
        }

        $recursive = [];
        foreach ($events as $nestedDefinition => $nested) {
            /*
             * contains now an array of arrays
             *
             * [
             *      [
             *          ProductManufacturerDefinition => [$entity,$entity,$entity,$entity,$entity],
             *          ProductPriceDefinition => [$entity,$entity,$entity,$entity,$entity]
             *      ]
             * ]
             */
            $recursive[] = $this->extractAssociations($nestedDefinition, $nested);
        }

        foreach ($recursive as $nested) {
            if (empty($nested)) {
                continue;
            }
            //iterate nested array of definitions and entities and merge them into root $events
            foreach ($nested as $nestedDefinition => $nestedEntities) {
                foreach ($nestedEntities as $nestedEntity) {
                    $events[$nestedDefinition][] = $nestedEntity;
                }
            }
        }

        return $events;
    }
}
