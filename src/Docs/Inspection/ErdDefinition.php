<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class ErdDefinition
{
    /**
     * @var EntityDefinition
     */
    private $definition;

    public function __construct(EntityDefinition $definition)
    {
        $this->definition = $definition;
    }

    public function isTranslation(): bool
    {
        return is_a(
            $this->definition,
            EntityTranslationDefinition::class,
            true
        );
    }

    public function isSpecialType(): bool
    {
        return $this->isMapping() || $this->isTranslation();
    }

    public function isMapping(): bool
    {
        return is_a($this->definition, MappingEntityDefinition::class, true);
    }

    public function toClassName(): string
    {
        return $this->definition->getClass();
    }

    public function entityName(): string
    {
        return $this->definition->getEntityName();
    }

    public function fields(): FieldCollection
    {
        return $this->definition->getFields();
    }

    public function toModuleName(): string
    {
        $parts = explode('\\', $this->definition->getClass());

        if (mb_strpos($this->definition->getClass(), 'Shopware\\Core') === 0) {
            $moduleName = implode('\\', \array_slice($parts, 0, 4));
        } else {
            $moduleName = implode('\\', \array_slice($parts, 0, 2));
        }

        return $moduleName;
    }
}
