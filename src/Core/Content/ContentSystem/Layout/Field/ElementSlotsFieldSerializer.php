<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
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

        if (\is_array($value)) {
            $value = $this->serializeSlots($value);
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * @return array<string, SlotContent>|null
     */
    public function decode(Field $field, mixed $value): ?array
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
     * Serializes slots array to format for storage.
     *
     * @param array<string, SlotContent> $slots
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function serializeSlots(array $slots): array
    {
        $data = [];

        foreach ($slots as $slotName => $slotContent) {
            if (!$slotContent instanceof SlotContent) {
                throw ContentSystemException::invalidFieldValueType(
                    "slots[{$slotName}]",
                    SlotContent::class,
                    get_debug_type($slotContent)
                );
            }

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
     * Deserializes slots data into array by recursively deserializing nested elements.
     *
     * @param array<string, array<int, array<string, mixed>>|array<string, mixed>> $slotsData
     *
     * @return array<string, SlotContent>
     */
    private function deserializeSlots(array $slotsData): array
    {
        $slots = [];

        foreach ($slotsData as $slotName => $slotData) {
            // Handle single element (has 'component' key indicating it's an element, not an array of elements)
            if (isset($slotData['component'])) {
                $element = $this->elementSerializer->decodeElement($slotData);
                $slots[$slotName] = new SlotContent([$element]);
                continue;
            }

            $elements = [];
            foreach ($slotData as $elementData) {
                if (!\is_array($elementData)) {
                    continue;
                }

                $elements[] = $this->elementSerializer->decodeElement($elementData);
            }
            $slots[$slotName] = new SlotContent($elements);
        }

        return $slots;
    }
}
