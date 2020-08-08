<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method FieldConfig[]    getIterator()
 * @method FieldConfig[]    getElements()
 * @method FieldConfig|null get(string $key)
 * @method FieldConfig|null first()
 * @method FieldConfig|null last()
 */
class FieldConfigCollection extends Collection
{
    /**
     * @param FieldConfig $element
     */
    public function add($element): void
    {
        $this->set($element->getName(), $element);
    }

    /**
     * @param string|int  $key
     * @param FieldConfig $element
     */
    public function set($key, $element): void
    {
        parent::set($element->getName(), $element);
    }

    public function getApiAlias(): string
    {
        return 'cms_data_resolver_field_config_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return FieldConfig::class;
    }
}
