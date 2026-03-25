<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-import-type ContentElementData from ContentElementFieldSerializer
 *
 * @phpstan-type SlotsData array<string, list<ContentElementData>>
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
        if (!$field instanceof ElementSlotsField) {
            throw ContentSystemException::invalidFieldType(ElementSlotsField::class, $field::class);
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
     * @param array<string, SlotContent> $slots
     *
     * @return SlotsData
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

    /**
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        $constraints = [
            new Type('array'),
            new All([
                new Type('array'),
                new Callback($this->validateSlotElements(...)),
            ]),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    protected function getConstraints(Field $field): array
    {
        return $this->buildConstraints($field);
    }

    /**
     * Validates each content element within a slot.
     * This enables deep validation of the recursive ContentElement tree structure.
     */
    private function validateSlotElements(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        $contentElementField = new ContentElementField('', '');
        $elementConstraints = $this->elementSerializer->buildConstraints($contentElementField);

        foreach ($value as $index => $elementData) {
            $violations = $context->getValidator()->validate($elementData, $elementConstraints);

            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                $path = "[$index]" . ($propertyPath !== '' ? ".$propertyPath" : '');

                $context->buildViolation((string) $violation->getMessage())
                    ->atPath($path)
                    ->addViolation();
            }
        }
    }

    /**
     * @param array<string, list<ContentElementData>> $slotsData
     *
     * @return array<string, SlotContent>
     */
    private function deserializeSlots(array $slotsData): array
    {
        $slots = [];

        foreach ($slotsData as $slotName => $slotData) {
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
