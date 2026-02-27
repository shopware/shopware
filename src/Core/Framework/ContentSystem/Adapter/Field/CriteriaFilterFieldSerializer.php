<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Field;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
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
 * @phpstan-import-type EqualsFilterType from QueryStringParser
 * @phpstan-import-type NotFilterType from QueryStringParser
 * @phpstan-import-type MultiFilterType from QueryStringParser
 * @phpstan-import-type ContainsFilterType from QueryStringParser
 * @phpstan-import-type PrefixFilterType from QueryStringParser
 * @phpstan-import-type SuffixFilterType from QueryStringParser
 * @phpstan-import-type RangeFilterType from QueryStringParser
 * @phpstan-import-type EqualsAnyFilterType from QueryStringParser
 *
 * @internal
 */
#[Package('discovery')]
class CriteriaFilterFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof CriteriaFilterField) {
            throw ContentSystemException::invalidFieldType(CriteriaFilterField::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value instanceof Filter) {
            $value = $this->serializeCriteriaFilter($value);
        } elseif ($value !== null && !\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType(
                $field->getPropertyName(),
                'Filter or array',
                \gettype($value)
            );
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, mixed $value): never
    {
        throw ContentSystemException::criteriaFilterFieldDecodeNotSupported();
    }

    /**
     * @return EqualsFilterType|NotFilterType|MultiFilterType|ContainsFilterType|PrefixFilterType|SuffixFilterType|RangeFilterType|EqualsAnyFilterType
     */
    public function serializeCriteriaFilter(Filter $filter): array
    {
        return QueryStringParser::toArray($filter);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function deserializeCriteriaFilter(array $data, EntityDefinition $definition, string $path = ''): Filter
    {
        $searchException = new SearchRequestException();
        $filter = QueryStringParser::fromArray($definition, $data, $searchException, $path);
        $searchException->tryToThrow();

        return $filter;
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
