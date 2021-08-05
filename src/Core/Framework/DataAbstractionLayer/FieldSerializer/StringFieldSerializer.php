<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StringFieldSerializer extends AbstractFieldSerializer
{
    private HtmlSanitizer $sanitizer;

    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        HtmlSanitizer $sanitizer
    ) {
        parent::__construct($validator, $definitionRegistry);

        $this->sanitizer = $sanitizer;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StringField) {
            throw new InvalidSerializerFieldException(StringField::class, $field);
        }

        if ($data->getValue() === '' && !$field->is(AllowEmptyString::class)) {
            $data->setValue(null);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $data->setValue($this->sanitize($this->sanitizer, $data, $field, $existence));

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $field->getStorageName() => $data->getValue() !== null ? (string) $data->getValue() : null;
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function decode(Field $field, /*?string */$value): ?string
    {
        return $value;
    }

    /**
     * @param StringField $field
     *
     * @return Constraint[]
     */
    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new Type('string'),
            new Length(['max' => $field->getMaxLength()]),
        ];

        if (!$field->is(AllowEmptyString::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }
}
