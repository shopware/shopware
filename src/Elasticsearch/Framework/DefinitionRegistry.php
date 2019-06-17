<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class DefinitionRegistry
{
    /**
     * @var EntityDefinition[]
     */
    private $definitions;

    public function __construct(iterable $definitions)
    {
        $this->definitions = $definitions;
    }

    public function isSupported(EntityDefinition $definition): bool
    {
        foreach ($this->definitions as $def) {
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

    public function getDefinitions(): iterable
    {
        return $this->definitions;
    }
}
