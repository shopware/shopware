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
    private StringField $idField;

    private StringField $componentField;

    private JsonField $propertiesField;

    private DataRequirementsField $dataRequirementsField;

    private ElementSlotsField $slotsField;

    private ContextProvidersField $providesContextField;

    private ContextConsumersField $acceptsContextField;

    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $this->idField = new StringField('id', 'id');
        $this->idField->addFlags(new Required());

        $this->componentField = new StringField('component', 'component');
        $this->componentField->addFlags(new Required());

        $this->propertiesField = new JsonField('properties', 'properties');
        $this->dataRequirementsField = new DataRequirementsField('data_requirements', 'dataRequirements');
        $this->slotsField = new ElementSlotsField('slots', 'slots');
        $this->providesContextField = new ContextProvidersField('provides_context', 'providesContext');
        $this->acceptsContextField = new ContextConsumersField('accepts_context', 'acceptsContext');

        parent::__construct($storageName, $propertyName, [
            $this->idField,
            $this->componentField,
            $this->propertiesField,
            $this->dataRequirementsField,
            $this->slotsField,
            $this->providesContextField,
            $this->acceptsContextField,
        ]);
    }

    /**
     * @return array{
     *     id: StringField,
     *     component: StringField,
     *     properties: JsonField,
     *     dataRequirements: DataRequirementsField,
     *     slots: ElementSlotsField,
     *     providesContext: ContextProvidersField,
     *     acceptsContext: ContextConsumersField
     * }
     */
    public function getNamedPropertyMapping(): array
    {
        return [
            'id' => $this->idField,
            'component' => $this->componentField,
            'properties' => $this->propertiesField,
            'dataRequirements' => $this->dataRequirementsField,
            'slots' => $this->slotsField,
            'providesContext' => $this->providesContextField,
            'acceptsContext' => $this->acceptsContextField,
        ];
    }

    protected function getSerializerClass(): string
    {
        return ContentElementFieldSerializer::class;
    }
}
