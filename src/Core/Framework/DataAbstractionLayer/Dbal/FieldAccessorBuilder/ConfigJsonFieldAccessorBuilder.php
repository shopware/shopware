<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ConfigJsonFieldAccessorBuilder extends JsonFieldAccessorBuilder
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof ConfigJsonField) {
            return null;
        }

        return parent::buildAccessor($root, $field, $context, $this->getStorageAccessor($field, $accessor));
    }

    public function castValue(string $value, Field $field, string $accessor): string
    {
        if (!$field instanceof ConfigJsonField) {
            return $value;
        }

        return parent::castValue($value, $field, $this->getStorageAccessor($field, $accessor));
    }

    private function getStorageAccessor(ConfigJsonField $field, string $accessor): string
    {
        $jsonPath = preg_replace(
            '#^' . preg_quote($field->getPropertyName(), '#') . '#',
            '',
            $accessor
        );

        return $field->getPropertyName() . '.' . ConfigJsonField::STORAGE_KEY . $jsonPath;
    }
}
