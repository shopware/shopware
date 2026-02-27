<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Field;

use Shopware\Core\Framework\ContentSystem\Adapter\ParameterBinding\ResolutionConfig;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
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
 * @phpstan-import-type EqualsFilterType from QueryStringParser
 * @phpstan-import-type NotFilterType from QueryStringParser
 * @phpstan-import-type MultiFilterType from QueryStringParser
 * @phpstan-import-type ContainsFilterType from QueryStringParser
 * @phpstan-import-type PrefixFilterType from QueryStringParser
 * @phpstan-import-type SuffixFilterType from QueryStringParser
 * @phpstan-import-type RangeFilterType from QueryStringParser
 * @phpstan-import-type EqualsAnyFilterType from QueryStringParser
 *
 * @phpstan-type ResolutionConfigData array{
 *     entity: string,
 *     match_field: string,
 *     constraints?: list<EqualsFilterType|NotFilterType|MultiFilterType|ContainsFilterType|PrefixFilterType|SuffixFilterType|RangeFilterType|EqualsAnyFilterType>
 * }
 *
 * @internal
 */
#[Package('framework')]
class ResolutionConfigFieldSerializer extends AbstractFieldSerializer
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
        if (!$field instanceof StorageAware) {
            throw ContentSystemException::invalidFieldType(StorageAware::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value instanceof ResolutionConfig) {
            $value = $this->serializeResolutionConfig($value);
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, mixed $value): ?ResolutionConfig
    {
        if (!$field instanceof ResolutionConfigField) {
            throw ContentSystemException::invalidFieldType(ResolutionConfigField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('resolution', 'array', \gettype($value));
        }

        return $this->deserializeResolutionConfig($value);
    }

    /**
     * @return ResolutionConfigData
     */
    public function serializeResolutionConfig(ResolutionConfig $config): array
    {
        $serializedResolutionConfig = [
            'entity' => $config->entity,
            'match_field' => $config->matchField,
        ];

        if ($config->constraints !== []) {
            $serializedConstraints = [];
            foreach ($config->constraints as $filter) {
                if ($filter instanceof Filter) {
                    $serializedConstraints[] = $this->filterSerializer->serializeCriteriaFilter($filter);
                    continue;
                }

                if (\is_array($filter)) {
                    $serializedConstraints[] = $filter;
                }
            }

            $serializedResolutionConfig['constraints'] = $serializedConstraints;
        }

        return $serializedResolutionConfig;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function deserializeResolutionConfig(array $data): ResolutionConfig
    {
        $entity = $data['entity'] ?? '';
        $matchField = $data['match_field'] ?? 'id';
        $constraintsData = $data['constraints'] ?? [];

        $filters = [];
        if ($entity !== '' && $constraintsData !== []) {
            $definition = $this->definitionRegistry->getByEntityName($entity);

            foreach ($constraintsData as $index => $constraintData) {
                if (!\is_array($constraintData)) {
                    continue;
                }

                $filters[] = $this->filterSerializer->deserializeCriteriaFilter(
                    $constraintData,
                    $definition,
                    '/constraints/' . $index
                );
            }
        }

        return new ResolutionConfig($entity, $matchField, $filters);
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
