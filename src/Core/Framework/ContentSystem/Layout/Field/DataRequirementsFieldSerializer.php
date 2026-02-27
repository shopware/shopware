<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
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
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-type DataRequirementData array{
 *   key: string,
 *   source: string,
 *   config: array<string, mixed>
 * }
 *
 * @internal
 */
#[Package('discovery')]
class DataRequirementsFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly DataLoaderConfigSerializerProvider $configProvider
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof DataRequirementsField) {
            throw ContentSystemException::invalidFieldType(DataRequirementsField::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if (!\is_array($value)) {
            yield $field->getStorageName() => Json::encode($value);

            return;
        }

        $serialized = array_map(function ($requirement) {
            return $requirement instanceof DataRequirement
                ? $this->serializeDataRequirement($requirement)
                : $requirement;
        }, $value);

        yield $field->getStorageName() => Json::encode($serialized);
    }

    /**
     * @return array<string, DataRequirement>|null
     */
    public function decode(Field $field, mixed $value): ?array
    {
        if (!$field instanceof DataRequirementsField) {
            throw ContentSystemException::invalidFieldType(DataRequirementsField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('data_requirements', 'array', \gettype($value));
        }

        return $this->deserializeDataRequirements($value);
    }

    /**
     * @return DataRequirementData
     */
    public function serializeDataRequirement(DataRequirement $requirement): array
    {
        return [
            'key' => $requirement->key,
            'source' => $requirement->source,
            'config' => $this->configProvider->encode($requirement->source, $requirement->config),
        ];
    }

    /**
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        $constraints = [
            new Type('array'),
            new All([
                new Collection(
                    fields: [
                        'key' => new Optional([new Type('string')]),
                        'source' => [new NotBlank(), new Type('string')],
                        'config' => new Optional([new Type('array')]),
                    ],
                    allowExtraFields: false,
                    allowMissingFields: false,
                ),
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
     * @param array<string, array<string, mixed>> $data
     *
     * @return array<string, DataRequirement>
     */
    private function deserializeDataRequirements(array $data): array
    {
        $requirements = [];

        foreach ($data as $key => $requirementData) {
            $requirementData['key'] = $requirementData['key'] ?? $key;
            $requirements[$key] = $this->deserializeDataRequirement($requirementData);
        }

        return $requirements;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeDataRequirement(array $data): DataRequirement
    {
        $source = $data['source'] ?? '';
        $configData = $data['config'] ?? [];

        $config = $this->configProvider->decode($source, $configData);

        return new DataRequirement($data['key'], $source, $config);
    }
}
