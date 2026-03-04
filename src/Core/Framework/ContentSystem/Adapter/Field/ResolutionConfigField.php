<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ResolutionConfigField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $propertyMapping = [
            new StringField('entity', 'entity'),
            new StringField('match_field', 'matchField'),
            new CriteriaFilterListField('constraints', 'constraints'),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return ResolutionConfigFieldSerializer::class;
    }
}
