<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-import-type ContentElementData from ContentElementFieldSerializer
 *
 * @internal
 */
#[Package('discovery')]
class ContentElementListFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly ContentElementFieldSerializer $contentElementSerializer
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

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if ($value instanceof ContentElement) {
            $value = [$value];
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType($field->getStorageName(), 'array', \gettype($value));
        }

        $serializedElements = [];
        foreach ($value as $element) {
            if ($element instanceof ContentElement) {
                $serializedElements[] = $this->contentElementSerializer->serializeContentElement($element);
                continue;
            }

            $serializedElements[] = $element;
        }

        yield $field->getStorageName() => Json::encode($serializedElements);
    }

    /**
     * @return array<ContentElement>|null
     */
    public function decode(Field $field, mixed $value): ?array
    {
        if (!$field instanceof ContentElementListField) {
            throw ContentSystemException::invalidFieldType(ContentElementListField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType($field->getStorageName(), 'array', \gettype($value));
        }

        if ($value === []) {
            return [];
        }

        // Validate indexed array format (multi-root layout)
        if (!\array_is_list($value)) {
            throw ContentSystemException::invalidFieldValueType(
                $field->getStorageName(),
                'indexed array of elements',
                'associative array'
            );
        }

        $storageName = $field->getStorageName();
        $elements = [];
        foreach ($value as $index => $elementData) {
            if (!\is_array($elementData)) {
                throw ContentSystemException::invalidFieldValueType(
                    "{$storageName}[$index]",
                    'array',
                    \gettype($elementData)
                );
            }

            $elements[] = $this->contentElementSerializer->decodeElement($elementData);
        }

        // TODO: Add validation to ensure element IDs are unique across all trees
        // Element IDs must be unique across all root elements and their descendants
        // to support partial rendering with ?elementId parameter

        return $elements;
    }

    /**
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        if (!$field instanceof ContentElementListField) {
            throw ContentSystemException::invalidFieldType(ContentElementListField::class, $field::class);
        }

        $contentElementField = new ContentElementField('', '');

        $constraints = [
            new Type('array'),
            new All(
                $this->contentElementSerializer->buildConstraints($contentElementField)
            ),
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
}
