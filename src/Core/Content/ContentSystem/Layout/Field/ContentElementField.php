<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ContentElementField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $propertyMapping = [
            (new StringField('id', 'id'))->addFlags(new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),
            new JsonField('properties', 'properties'),
            new DataRequirementsField('data_requirements', 'dataRequirements'),
            new ElementSlotsField('slots', 'slots'),
            new ContextProvidersField('provides_context', 'providesContext'),
            new ContextConsumersField('accepts_context', 'acceptsContext'),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return ContentElementFieldSerializer::class;
    }
}
