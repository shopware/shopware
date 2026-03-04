<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Field;

use Shopware\Core\Framework\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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
 * @internal
 */
#[Package('framework')]
class ParameterBindingsFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly ParameterBindingFieldSerializer $bindingSerializer
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
            $serialized = [];
            foreach ($value as $paramName => $binding) {
                if ($binding instanceof ParameterBinding) {
                    $serialized[$paramName] = $this->bindingSerializer->serializeParameterBinding($binding);
                } else {
                    $serialized[$paramName] = $binding;
                }
            }
            $value = $serialized;
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * @return array<string, ParameterBinding>|null
     */
    public function decode(Field $field, mixed $value): ?array
    {
        if (!$field instanceof ParameterBindingsField) {
            throw ContentSystemException::invalidFieldType(ParameterBindingsField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('parameter_bindings', 'array', \gettype($value));
        }

        return $this->deserializeParameterBindings($value);
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
     * @param array<string, array<string, mixed>> $data
     *
     * @return array<string, ParameterBinding>
     */
    private function deserializeParameterBindings(array $data): array
    {
        $bindings = [];

        foreach ($data as $paramName => $bindingData) {
            $bindings[$paramName] = $this->bindingSerializer->deserializeParameterBinding($bindingData, $paramName);
        }

        return $bindings;
    }
}
