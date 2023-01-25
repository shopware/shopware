<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @deprecated tag:v6.6.0 - Will be removed use `defaults: {"_entity"="entityName"}` instead
 */
#[Package('core')]
class Entity
{
    private string $value;

    /**
     * @param array{value: string} $values
     */
    public function __construct(array $values)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        $this->value = $values['value'];
    }

    public function getAliasName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return 'entity';
    }

    public function allowArray(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return false;
    }

    public function getValue(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return $this->value;
    }

    public function setValue(string $entity): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        $this->value = $entity;
    }
}
