<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class EntityLoadedEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var Entity[]
     */
    protected $entities;

    /**
     * @var EntityDefinition
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

    /**
     * @var bool
     */
    protected $nested = true;

    public function __construct(EntityDefinition $definition, array $entities, Context $context, bool $nested = true)
    {
        $this->entities = $entities;
        $this->definition = $definition;
        $this->context = $context;
        $this->name = $this->definition->getEntityName() . '.loaded';
        $this->nested = $nested;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getDefinition(): EntityDefinition
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
        if (!$this->nested) {
            return null;
        }

        $associations = $this->extractAssociations($this->definition, $this->entities);

        $events = [];
        foreach ($associations as $association) {
            $events[] = $this->createNested($association['definition'], $association['entities']);
        }

        return new NestedEventCollection($events);
    }

    public function getIds(): array
    {
        $ids = [];

        /** @var Entity $entity */
        foreach ($this->getEntities() as $entity) {
            $ids[] = $entity->getUniqueIdentifier();
        }

        return $ids;
    }

    protected function extractAssociations(EntityDefinition $definition, iterable $entities): array
    {
        $events = $this->extractAssociationsInCurrentLevel($definition, $entities);
        $recursive = $this->loadRecursivelyNestedAssociations($events);
        $events = $this->mergeIntoEvents($recursive, $events);

        return $events;
    }

    protected function createNested(EntityDefinition $definition, array $entities): EntityLoadedEvent
    {
        return new EntityLoadedEvent($definition, $entities, $this->context, false);
    }

    private function extractAssociationsInCurrentLevel(EntityDefinition $definition, iterable $entities): array
    {
        $associations = $definition->getFields();

        $events = [];
        foreach ($associations as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }

            $isExtension = $association->is(Extension::class);

            if ($association instanceof ManyToOneAssociationField || $association instanceof OneToOneAssociationField) {
                /** @var Entity $entity */
                foreach ($entities as $entity) {
                    try {
                        if ($isExtension) {
                            $reference = $entity->getExtension($association->getPropertyName());
                        } else {
                            $reference = $entity->get($association->getPropertyName());
                        }
                    } catch (\InvalidArgumentException $e) {
                        continue;
                    }

                    if ($reference) {
                        $associatedDefinition = $association->getReferenceDefinition();
                        $associationClass = $associatedDefinition->getClass();

                        if (!isset($events[$associationClass])) {
                            $events[$associationClass] = [
                                'definition' => $associatedDefinition,
                                'entities' => [],
                            ];
                        }

                        $events[$associationClass]['entities'][] = $reference;
                    }
                }

                continue;
            }

            $referenceDefinition = $association->getReferenceDefinition();
            if ($association instanceof ManyToManyAssociationField) {
                $referenceDefinition = $association->getToManyReferenceDefinition();
            }

            foreach ($entities as $entity) {
                try {
                    if ($isExtension) {
                        $references = $entity->getExtension($association->getPropertyName());
                    } else {
                        $references = $entity->get($association->getPropertyName());
                    }
                } catch (\InvalidArgumentException $e) {
                    continue;
                }

                if (empty($references)) {
                    continue;
                }

                $referenceDefinitionClass = $referenceDefinition->getClass();

                if (!isset($events[$referenceDefinitionClass])) {
                    $events[$referenceDefinitionClass] = [
                        'definition' => $referenceDefinition,
                        'entities' => [],
                    ];
                }

                foreach ($references as $reference) {
                    $events[$referenceDefinitionClass]['entities'][] = $reference;
                }
            }
        }

        return $events;
    }

    private function loadRecursivelyNestedAssociations(array $events): array
    {
        $recursive = [];

        foreach ($events as $nested) {
            /*
             * contains now an array of arrays
             *
             * [
             *      [
             *          ProductManufacturerDefinition => ['definition' =>  $definition, 'entities' => [$entity,$entity,$entity,$entity,$entity]],
             *          ProductPriceDefinition => ['definition' =>  $definition, 'entities' => [$entity,$entity,$entity,$entity,$entity]]
             *      ]
             * ]
             */
            $recursive[] = $this->extractAssociations($nested['definition'], $nested['entities']);
        }

        return $recursive;
    }

    private function mergeIntoEvents(array $recursive, array $events): array
    {
        foreach ($recursive as $nested) {
            if (empty($nested)) {
                continue;
            }

            //iterate nested array of definitions and entities and merge them into root $events
            foreach ($nested as $nestedDefinition => $nestedCollection) {
                if (!isset($events[$nestedDefinition])) {
                    $events[$nestedDefinition] = $nestedCollection;

                    continue;
                }

                foreach ($nestedCollection['entities'] as $nestedEntity) {
                    $events[$nestedDefinition]['entities'][] = $nestedEntity;
                }
            }
        }

        return $events;
    }
}
