<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class ErdDefinition
{
    /**
     * @var EntityDefinition|string
     */
    private $definitionClass;

    public function __construct(string $definitionClass)
    {
        $this->definitionClass = $definitionClass;
    }

    public function isTranslation(): bool
    {
        return is_a(
            $this->definitionClass,
            EntityTranslationDefinition::class,
            true
        );
    }

    public function isSpecialType(): bool
    {
        if ($this->isMapping()
            || $this->isTranslation()
        ) {
            return true;
        }

        return false;
    }

    public function isMapping(): bool
    {
        return is_a($this->definitionClass, MappingEntityDefinition::class, true);
    }

    public function toClassName(): string
    {
        return $this->definitionClass;
    }

    public function entityName(): string
    {
        return $this->definitionClass->getEntityName();
    }

    public function fields(): FieldCollection
    {
        return $this->definitionClass->getFields();
    }

    public function toModuleName(): string
    {
        $parts = explode('\\', $this->definitionClass);

        if (strpos($this->definitionClass, 'Shopware\\Core') === 0) {
            $moduleName = implode('\\', array_slice($parts, 0, 4));
        } else {
            $moduleName = implode('\\', array_slice($parts, 0, 2));
        }

        return $moduleName;
    }
}
