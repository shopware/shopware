<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPriceFieldTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class PriceDefinitionFieldSerializer extends JsonFieldSerializer
{
    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $compositeHandler,
        ValidatorInterface $validator,
        private readonly RuleConditionRegistry $ruleConditionRegistry
    ) {
        parent::__construct($validator, $compositeHandler);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = \json_decode(\json_encode($data->getValue(), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        if ($value !== null) {
            if (!\array_key_exists('type', $value)) {
                throw new InvalidPriceFieldTypeException('none');
            }

            switch ($value['type']) {
                case QuantityPriceDefinition::TYPE:
                    $this->validateProperties(
                        $value,
                        QuantityPriceDefinition::getConstraints(),
                        $parameters->getPath()
                    );
                    if (!\array_key_exists('taxRules', $value)) {
                        break;
                    }

                    foreach ($value['taxRules'] as $key => $taxRule) {
                        $this->validateProperties($taxRule, TaxRule::getConstraints(), $parameters->getPath() . '/taxRules/' . $key);
                    }

                    break;
                case AbsolutePriceDefinition::TYPE:
                    $this->validateProperties(
                        $value,
                        AbsolutePriceDefinition::getConstraints(),
                        $parameters->getPath()
                    );

                    if (!\array_key_exists('filter', $value) || $value['filter'] === null) {
                        break;
                    }
                    $violations = $this->validateRules($value['filter'], $parameters->getPath() . '/filter');
                    if ($violations->count() > 0) {
                        throw new WriteConstraintViolationException($violations, $parameters->getPath());
                    }

                    break;
                case CurrencyPriceDefinition::TYPE:
                    $this->validateProperties(
                        $value,
                        CurrencyPriceDefinition::getConstraints(),
                        $parameters->getPath()
                    );
                    if (!\array_key_exists('filter', $value) || $value['filter'] === null) {
                        break;
                    }

                    $violations = $this->validateRules($value['filter'], $parameters->getPath() . '/filter');
                    if ($violations->count() > 0) {
                        throw new WriteConstraintViolationException($violations, $parameters->getPath());
                    }

                    break;
                case PercentagePriceDefinition::TYPE:
                    $this->validateProperties(
                        $value,
                        PercentagePriceDefinition::getConstraints(),
                        $parameters->getPath()
                    );

                    if (!\array_key_exists('filter', $value) || $value['filter'] === null) {
                        break;
                    }
                    $violations = $this->validateRules($value['filter'], $parameters->getPath() . '/filter');
                    if ($violations->count() > 0) {
                        throw new WriteConstraintViolationException($violations, $parameters->getPath());
                    }

                    break;
                default:
                    throw new InvalidPriceFieldTypeException($value['type']);
            }

            unset($value['extensions']);
        }

        $data->setValue($value);

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): ?PriceDefinitionInterface
    {
        if ($value === null) {
            return null;
        }

        $decoded = parent::decode($field, $value);
        if (!\is_array($decoded)) {
            return null;
        }

        if (!\array_key_exists('type', $decoded)) {
            throw new InvalidPriceFieldTypeException('none');
        }

        switch ($decoded['type']) {
            case QuantityPriceDefinition::TYPE:
                return QuantityPriceDefinition::fromArray($decoded);
            case AbsolutePriceDefinition::TYPE:
                return new AbsolutePriceDefinition($decoded['price'], $this->decodeFilter($decoded));
            case CurrencyPriceDefinition::TYPE:
                $collection = new PriceCollection();
                foreach ($decoded['price'] as $price) {
                    $collection->add(new Price($price['currencyId'], (float) $price['net'], (float) $price['gross'], (bool) $price['linked']));
                }

                return new CurrencyPriceDefinition($collection, $this->decodeFilter($decoded));
            case PercentagePriceDefinition::TYPE:
                return new PercentagePriceDefinition($decoded['percentage'], $this->decodeFilter($decoded));
        }

        throw new InvalidPriceFieldTypeException($decoded['type']);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function decodeFilter(array $decoded): ?Rule
    {
        if (!\array_key_exists('filter', $decoded) || $decoded['filter'] === null) {
            return null;
        }

        return $this->decodeRule($decoded['filter']);
    }

    private function validateRules(array $data, string $basePath): ConstraintViolationList
    {
        $violationList = new ConstraintViolationList();
        $type = null;
        if (\array_key_exists('_name', $data)) {
            $type = $data['_name'];
            unset($data['_name']);
        }

        if ($type === null) {
            $violationList->add(
                $this->buildViolation(
                    'This "_name" value (%value%) is invalid.',
                    ['%value%' => 'NULL'],
                    $basePath . '/_name'
                )
            );
        } elseif ($this->ruleConditionRegistry->has($type)) {
            $rule = $this->ruleConditionRegistry->getRuleInstance($type);
            // do not validate container
            if (!$rule instanceof Container) {
                $rule->assign($data);
                $validations = $rule->getConstraints();
                $violationList->addAll($this->validateConsistence($basePath, $validations, $data));
            }
        }
        // else: the condition type is not registered (e.g. a plugin contributing it was uninstalled).
        // We keep the stored payload verbatim instead of rejecting the whole write, so existing orders
        // stay readable, versionable and recalculable. Such conditions are substituted with an
        // UnknownConditionRule on decode (see decodeRule()) and evaluate to false at calculation time.
        //
        // Trade-off: a clone/version write re-emits the original payload as plain JSON, so it is
        // indistinguishable here from a *new* write that references an unknown condition name (e.g. via
        // the Admin API). Both are therefore accepted rather than rejected. This is intentional - orders
        // must stay writable - and safe, because an unknown condition is fail-closed (matches nothing).

        if (\array_key_exists('rules', $data)) {
            foreach ($data['rules'] as $rule) {
                $violationList->addAll($this->validateRules($rule, $basePath . '/' . $type));
            }
        }

        return $violationList;
    }

    private function decodeRule(array $rule): Rule
    {
        if (!$this->ruleConditionRegistry->has($rule['_name'])) {
            // Keep the full original payload so a plain re-encode (read, order versioning, normal save)
            // preserves it verbatim and it is restored once the contributing plugin is reinstalled.
            // Recalculation recomputes the cart and may drop a discount whose placeholder matches nothing.
            return new UnknownConditionRule($rule);
        }

        $ruleClass = $this->ruleConditionRegistry->getRuleClass($rule['_name']);
        $object = new $ruleClass();

        if (\array_key_exists('rules', $rule) && $object instanceof Container) {
            foreach ($rule['rules'] as $item) {
                $object->addRule($this->decodeRule($item));
            }
        } else {
            $object->assign($rule);
        }

        return $object;
    }

    private function validateProperties(array $data, array $constraints, string $path): void
    {
        foreach ($constraints as $key => $constraint) {
            $value = $data[$key] ?? null;

            $this->validate(
                $constraint,
                new KeyValuePair($key, $value, true),
                $path
            );
        }
    }

    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        ?string $propertyPath = null
    ): ConstraintViolation {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            null
        );
    }

    private function validateConsistence(string $basePath, array $fieldValidations, array $payload): ConstraintViolationList
    {
        $list = new ConstraintViolationList();
        foreach ($fieldValidations as $fieldName => $validations) {
            $currentPath = \sprintf('%s/%s', $basePath, $fieldName);
            $list->addAll(
                $this->validator->startContext()
                    ->atPath($currentPath)
                    ->validate($payload[$fieldName] ?? null, $validations)
                    ->getViolations()
            );
        }

        foreach ($payload as $fieldName => $_value) {
            $currentPath = \sprintf('%s/%s', $basePath, $fieldName);

            if (!\array_key_exists($fieldName, $fieldValidations)) {
                $list->add(
                    $this->buildViolation(
                        'The property "{{ fieldName }}" is not allowed.',
                        ['{{ fieldName }}' => $fieldName],
                        $currentPath
                    )
                );
            }
        }

        return $list;
    }
}
