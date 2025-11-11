<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ParameterBindingField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $propertyMapping = [
            new StringField('placeholder', 'placeholder'),
            new ResolutionConfigField('resolution', 'resolution'),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return ParameterBindingFieldSerializer::class;
    }
}
