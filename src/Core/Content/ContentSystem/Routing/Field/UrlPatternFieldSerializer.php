<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
class UrlPatternFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly StringFieldSerializer $stringFieldSerializer
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = $data->getValue();

        if (\is_string($value) && $value !== '') {
            $normalized = '/' . ltrim($value, '/');
            $data->setValue($normalized);
        }

        yield from $this->stringFieldSerializer->encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): ?string
    {
        return $this->stringFieldSerializer->decode($field, $value);
    }

    protected function getConstraints(Field $field): array
    {
        if (!$field instanceof UrlPatternField) {
            throw ContentSystemException::invalidFieldType(UrlPatternField::class, $field::class);
        }

        return $this->stringFieldSerializer->getConstraints($field);
    }
}
