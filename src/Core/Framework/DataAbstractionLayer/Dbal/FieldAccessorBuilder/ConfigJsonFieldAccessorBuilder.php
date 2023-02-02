<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class ConfigJsonFieldAccessorBuilder extends JsonFieldAccessorBuilder
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof ConfigJsonField) {
            return null;
        }

        $jsonPath = preg_replace(
            '#^' . preg_quote($field->getPropertyName(), '#') . '#',
            '',
            $accessor
        );

        $accessor = $field->getPropertyName() . '.' . ConfigJsonField::STORAGE_KEY . $jsonPath;

        return parent::buildAccessor($root, $field, $context, $accessor);
    }
}
