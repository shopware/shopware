<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\ElementSlots;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Serializes ElementSlots to/from JSON with recursive ContentElement handling.
 *
 * @internal
 */
#[Package('discovery')]
class ElementSlotsFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly ContentElementFieldSerializer $elementSerializer
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw ContentSystemException::invalidFieldType(StorageAware::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value instanceof ElementSlots) {
            $value = $this->serializeElementSlots($value);
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, mixed $value): ?ElementSlots
    {
        if (!$field instanceof ElementSlotsField) {
            throw ContentSystemException::invalidFieldType(ElementSlotsField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('slots', 'array', \gettype($value));
        }

        return $this->deserializeSlots($value);
    }

    /**
     * Serializes ElementSlots to array format for storage.
     * Public to allow ContentElementFieldSerializer to use it for recursive serialization.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function serializeElementSlots(ElementSlots $slots): array
    {
        $data = [];

        foreach ($slots as $slotName => $slotContent) {
            $elements = [];
            foreach ($slotContent as $element) {
                $elements[] = $this->elementSerializer->serializeContentElement($element);
            }
            $data[$slotName] = $elements;
        }

        return $data;
    }

    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new Type('array'),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    /**
     * Deserializes slots data into ElementSlots by recursively deserializing nested elements.
     *
     * @param array<string, array<int, array<string, mixed>>|array<string, mixed>> $slotsData
     */
    private function deserializeSlots(array $slotsData): ElementSlots
    {
        $slots = [];

        foreach ($slotsData as $slotName => $slotData) {
            // Handle single element (has 'type' key indicating it's an element, not an array of elements)
            if (isset($slotData['type'])) {
                $element = $this->elementSerializer->decodeElement($slotData);
                $slots[$slotName] = new SlotContent([$element]);
            } else {
                // Handle multiple elements
                $elements = [];
                foreach ($slotData as $elementData) {
                    if (\is_array($elementData)) {
                        $elements[] = $this->elementSerializer->decodeElement($elementData);
                    }
                }
                $slots[$slotName] = new SlotContent($elements);
            }
        }

        return new ElementSlots($slots);
    }
}
