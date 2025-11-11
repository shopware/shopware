<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Decode unsupported because entity context is required. Use ResolutionConfigField instead.
 *
 * @internal
 */
#[Package('discovery')]
class CriteriaFilterListFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly CriteriaFilterFieldSerializer $filterSerializer
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof CriteriaFilterListField) {
            throw ContentSystemException::invalidFieldType(CriteriaFilterListField::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if (\is_array($value)) {
            $arrayValue = [];
            foreach ($value as $item) {
                if ($item instanceof Filter) {
                    $item = $this->filterSerializer->serializeCriteriaFilter($item);
                }

                if (!\is_array($item)) {
                    throw ContentSystemException::invalidFieldValueType(
                        $field->getPropertyName(),
                        'Filter or array',
                        \gettype($item)
                    );
                }

                $arrayValue[] = $item;
            }
            $value = $arrayValue;
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * Decode unsupported - entity context required. Use CriteriaFilterFieldSerializer::deserializeCriteriaFilter().
     */
    public function decode(Field $field, mixed $value): never
    {
        throw ContentSystemException::criteriaFilterFieldDecodeNotSupported();
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
}
