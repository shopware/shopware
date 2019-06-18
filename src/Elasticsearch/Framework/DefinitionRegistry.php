<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Elasticsearch\Framework\Event\CollectDefinitionsEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DefinitionRegistry
{
    /**
     * @var EntityDefinition[]
     */
    private $definitions;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        DefinitionInstanceRegistry $registry
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->registry = $registry;
    }

    public function isSupported(EntityDefinition $definition): bool
    {
        foreach ($this->getDefinitions() as $def) {
            if ($def instanceof $definition) {
                return true;
            }
            if ($definition instanceof $def) {
                return true;
            }
        }

        return false;
    }

    public function getIndex(EntityDefinition $definition, Context $context): string
    {
        return $definition->getEntityName() . '_' . $context->getLanguageId();
    }

    public function getDefinitions(): array
    {
        if (!$this->definitions) {
            $this->definitions = [];

            $event = new CollectDefinitionsEvent();

            $this->eventDispatcher->dispatch($event);

            foreach ($event->getDefinitions() as $class) {
                $this->definitions[$class] = $this->registry->get($class);
            }
        }

        return $this->definitions;
    }
}
