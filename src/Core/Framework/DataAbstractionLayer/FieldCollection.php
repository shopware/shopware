<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void       set(string $key, Field $entity)
 * @method Field|null first()
 * @method Field|null last()
 */
class FieldCollection extends Collection
{
    /**
     * @var Field[]
     */
    protected $mappedByStorageName = [];

    /**
     * @var DefinitionInstanceRegistry|null
     */
    private $registry;

    private $protoFields = [];

    public function __construct(iterable $elements = [], ?DefinitionInstanceRegistry $registry = null)
    {
        $this->registry = $registry;
        parent::__construct($elements);
    }

    public function __debugInfo()
    {
        return [
            array_keys($this->elements),
            array_keys($this->mappedByStorageName),
        ];
    }

    /**
     * @param Field $field
     */
    public function add($field): void
    {
        $this->validateType($field);
        $this->protoFields[] = $field;

        if ($this->registry === null) {
            return;
        }

        $this->compile($this->registry);
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        $this->registry = $registry;

        /** @var Field $field */
        foreach ($this->protoFields as $field) {
            $field->compile($registry);

            $this->elements[$field->getPropertyName()] = $field;
            if ($field instanceof StorageAware && !$field->getFlag(Deferred::class)) {
                $this->mappedByStorageName[$field->getStorageName()] = $field;
            }
        }
        $this->protoFields = [];
    }

    /**
     * @param string $fieldName
     *
     * @internal
     */
    public function remove($fieldName): void
    {
        if (isset($this->mappedByStorageName[$fieldName])) {
            unset($this->mappedByStorageName[$fieldName]);
        }

        parent::remove($fieldName);
    }

    public function get($propertyName): ?Field
    {
        $this->checkCompiledSingle();

        return $this->elements[$propertyName] ?? null;
    }

    public function getBasicFields(): self
    {
        return $this->filter(
            function (Field $field) {
                if ($field instanceof AssociationField) {
                    return $field->getAutoload();
                }

                return true;
            }
        );
    }

    public function getMappedByStorageName()
    {
        $this->checkCompiledSingle();

        return array_keys($this->mappedByStorageName);
    }

    public function getByStorageName(string $storageName): ?Field
    {
        $this->checkCompiledSingle();

        return $this->mappedByStorageName[$storageName] ?? null;
    }

    public function filterByFlag(string $flagClass): self
    {
        $this->checkCompiledSingle();

        $ret = $this->filter(function (Field $field) use ($flagClass) {
            return $field->is($flagClass);
        });

        return $ret;
    }

    public function isCompiled(): bool
    {
        return count($this->protoFields) === 0;
    }

    /**
     * @return \Generator|Field[]
     */
    public function getIterator(): \Generator
    {
        $this->checkCompiledMultiple();

        yield from parent::getIterator();
    }

    /**
     * @return array|Field[]
     */
    public function getElements(): array
    {
        $this->checkCompiledMultiple();

        return parent::getElements();
    }

    protected function createNew(iterable $elements = [])
    {
        $newCollection = new self($elements, $this->registry);
        $newCollection->compile($this->registry);

        return $newCollection;
    }

    protected function getExpectedClass(): ?string
    {
        return Field::class;
    }

    private function checkCompiledSingle(): void
    {
        if (!$this->isCompiled()) {
            throw new \BadMethodCallException('Unable to inspect an uncompiled field collection');
        }
    }

    private function checkCompiledMultiple(): void
    {
        if (!$this->isCompiled()) {
            throw new \BadMethodCallException('Unable to iterate over an uncompiled field collection');
        }
    }
}
