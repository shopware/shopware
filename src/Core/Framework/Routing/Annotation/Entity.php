<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Shopware\Core\Framework\Feature;

/**
 * @Annotation
 *
 * @package core
 *
 * @deprecated tag:v6.6.0 - Will be removed use `defaults: {"_entity"="entityName"}` instead
 */
class Entity
{
    private string $value;

    public function getAliasName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return 'entity';
    }

    public function allowArray(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return false;
    }

    public function getValue(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return $this->value;
    }

    public function setValue(string $entity): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        $this->value = $entity;
    }
}
