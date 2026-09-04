<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The write-side constraint descriptor over one element's context wiring: its `providesContext` map, its
 * `acceptsContext` map, and the one rule that spans both. {@see StoredTreeConstraints} composes an instance
 * and attaches what it returns; nothing here reads a registry, so the whole cluster is stateless and
 * dependency-free.
 *
 * The decode-side counterpart is {@see ContextWiringDecoder}, and the render-side one is
 * {@see WiringPlanner}. The three are deliberately independent implementations of the same rules rather than
 * one shared implementation: StoredTreeShapeConformanceTest runs the write descriptor and the codec over one
 * payload table precisely to catch a divergence that sharing would hide instead of surface.
 *
 * The composition helpers {@see nonNull()} and {@see stringKeyedMap()} are duplicated from
 * {@see StoredTreeConstraints} rather than shared, keeping this class free of collaborators.
 *
 * @internal
 */
#[Package('framework')]
final class StoredTreeWiringConstraints
{
    /**
     * A provider entry is itself a string-keyed map — the two declared fields plus the declared strategy's
     * own fields — so it carries the key check its {@see ContextWiringDecoder} counterpart applies to it.
     *
     * @return list<Constraint>
     */
    public function contextProviderConstraints(): array
    {
        return $this->stringKeyedMap(
            ...$this->nonNull(
                new Type('array'),
                new Callback($this->validateStringKeys(...)),
                new Collection(
                    fields: [
                        'type' => [new NotBlank(), new Choice(choices: ContextType::values())],
                        'distribution' => [new NotBlank(), new Choice(choices: DistributionStrategy::values())],
                    ],
                    allowExtraFields: true,
                    allowMissingFields: false
                ),
                new Callback($this->validateDistributionFields(...)),
            )
        );
    }

    /**
     * @return list<Constraint>
     */
    public function contextConsumerConstraints(): array
    {
        return [
            ...$this->stringKeyedMap(
                ...$this->nonNull(
                    new Type('array'),
                    new Collection(
                        fields: [
                            'type' => [new NotBlank(), new Choice(choices: ContextType::values())],
                            'required' => $this->nonNull(new Type('bool')),
                            'redistribute' => new Optional([new Type('bool')]),
                            'consumerAlias' => new Optional([new Type('string')]),
                            'propertyAlias' => new Optional([new Type('string')]),
                        ],
                        allowExtraFields: false,
                        allowMissingFields: false
                    ),
                    new Callback($this->validateConsumerAliases(...)),
                )
            ),
            // Map-level, because both rules are judged per entry against the entry's own map key, which a
            // constraint inside the `All()` above never sees.
            new Callback($this->validateConsumerLandingKeys(...)),
            new Callback($this->validateRedistributeKeyShape(...)),
        ];
    }

    /**
     * The provider a redistributing consumer derives is keyed by `propertyAlias ?? contextKey`, so an authored
     * provider already holding that key would be silently overwritten. A `consumerAlias` renames only what
     * children match on and never enters this comparison.
     *
     * The rule spans both wiring maps of one element, which is why {@see StoredTreeConstraints} attaches it
     * whole-element rather than beside the consumer map's own rules.
     */
    public function validateRedistributeProviderConflicts(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        $consumers = $value['acceptsContext'] ?? [];
        $providers = $value['providesContext'] ?? [];

        if (!\is_array($consumers) || !\is_array($providers)) {
            return;
        }

        foreach ($consumers as $contextKey => $consumer) {
            if (!\is_string($contextKey) || !\is_array($consumer)) {
                continue;
            }

            if (($consumer['redistribute'] ?? false) !== true) {
                continue;
            }

            $propertyAlias = $consumer['propertyAlias'] ?? null;
            if ($propertyAlias !== null && !\is_string($propertyAlias)) {
                continue;
            }

            $providerKey = $propertyAlias ?? $contextKey;

            if (!\array_key_exists($providerKey, $providers)) {
                continue;
            }

            $context->buildViolation('This consumer redistributes under the provider key {{ key }}, which an authored provider already holds.')
                ->setParameter('{{ key }}', $providerKey)
                ->atPath('[acceptsContext][' . $contextKey . '][redistribute]')
                ->addViolation();
        }
    }

    /**
     * The fields a provider carries beyond `type` and `distribution` depend on the declared strategy, which the
     * `Collection` above admits as extra fields; each strategy's own constraint set is applied here.
     */
    private function validateDistributionFields(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value) || !isset($value['distribution'])) {
            return;
        }

        $configClass = match ($value['distribution']) {
            'broadcast' => BroadcastDistributionConfig::class,
            'indexed' => IndexedDistributionConfig::class,
            'iterator' => IteratorDistributionConfig::class,
            'keyed' => KeyedDistributionConfig::class,
            'sliced' => SlicedDistributionConfig::class,
            default => null,
        };

        if ($configClass === null) {
            return;
        }

        foreach ($configClass::buildConstraints() as $fieldName => $fieldConstraints) {
            $fieldValue = $value[$fieldName] ?? null;

            foreach ($fieldConstraints as $constraint) {
                $violations = $context->getValidator()->validate($fieldValue, $constraint);

                foreach ($violations as $violation) {
                    $context->buildViolation((string) $violation->getMessage())
                        ->atPath("[$fieldName]")
                        ->addViolation();
                }
            }
        }
    }

    /**
     * The two cross-field consumer rules {@see ContextWiringDecoder} enforces on decode: a consumer alias
     * renames the context this consumer redistributes, so it means nothing without `redistribute`, and a
     * property alias names one property on this element, so it carries no dot notation.
     */
    private function validateConsumerAliases(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        if (($value['consumerAlias'] ?? null) !== null && ($value['redistribute'] ?? false) !== true) {
            $context->buildViolation('This value requires "redistribute" to be true.')
                ->atPath('[consumerAlias]')
                ->addViolation();
        }

        $propertyAlias = $value['propertyAlias'] ?? null;
        if (\is_string($propertyAlias) && str_contains($propertyAlias, '.')) {
            $context->buildViolation('This value should be a simple property name without dot notation.')
                ->atPath('[propertyAlias]')
                ->addViolation();
        }
    }

    /**
     * The landing key a consumer writes its delivered value to is the base segment of
     * `propertyAlias ?? contextKey`, and two consumers of one element writing the same one would each
     * overwrite the other. {@see ContextWiringDecoder} throws on the first collision; here every colliding
     * consumer is reported, and the first holder of a base key keeps it so a third consumer on that key is
     * reported too.
     *
     * A shape another constraint already reports is skipped rather than reported twice: a non-array map, a
     * non-array entry, a non-string map key, a non-string `propertyAlias`.
     */
    private function validateConsumerLandingKeys(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        $holders = [];

        foreach ($value as $contextKey => $consumer) {
            if (!\is_string($contextKey) || !\is_array($consumer)) {
                continue;
            }

            $propertyAlias = $consumer['propertyAlias'] ?? null;
            if ($propertyAlias !== null && !\is_string($propertyAlias)) {
                continue;
            }

            $propertyKey = $propertyAlias ?? $contextKey;
            $baseKey = str_contains($propertyKey, '.')
                ? substr($propertyKey, 0, (int) strpos($propertyKey, '.'))
                : $propertyKey;

            if (\array_key_exists($baseKey, $holders)) {
                $context->buildViolation('This consumer writes the property key {{ key }}, which context {{ first }} already writes.')
                    ->setParameter('{{ key }}', $baseKey)
                    ->setParameter('{{ first }}', $holders[$baseKey])
                    ->atPath('[' . $contextKey . ']')
                    ->addViolation();

                continue;
            }

            $holders[$baseKey] = $contextKey;
        }
    }

    /**
     * A redistributing consumer becomes a provider keyed by what it writes, and a dotted context key names a
     * path into a delivered value rather than a key a provider could carry, so the two cannot be combined.
     * The map key is what carries the dot, which is why this is judged here and not per entry.
     */
    private function validateRedistributeKeyShape(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        foreach ($value as $contextKey => $consumer) {
            if (!\is_string($contextKey) || !\is_array($consumer)) {
                continue;
            }

            if (($consumer['redistribute'] ?? false) !== true || !str_contains($contextKey, '.')) {
                continue;
            }

            $context->buildViolation('This context key uses dot notation and cannot be redistributed.')
                ->atPath('[' . $contextKey . '][redistribute]')
                ->addViolation();
        }
    }

    /**
     * Symfony's `Type`, `Collection` and `All` all skip a null value, so a type assertion on its own admits a
     * null that decode rejects. Every value whose decode counterpart requires one is built through here.
     *
     * @return list<Constraint>
     */
    private function nonNull(Constraint ...$constraints): array
    {
        return array_values([new NotNull(), ...$constraints]);
    }

    /**
     * One string-keyed wiring map: the container itself, its key types, and — when given — the constraints
     * every entry carries.
     *
     * @return list<Constraint>
     */
    private function stringKeyedMap(Constraint ...$entryConstraints): array
    {
        $constraints = [new Type('array'), new Callback($this->validateStringKeys(...))];

        if ($entryConstraints !== []) {
            $constraints[] = new All($entryConstraints);
        }

        return $constraints;
    }

    /**
     * Matches the codec's own non-string key rejection. PHP maps a numeric JSON member name back to an
     * integer array key, so this rejects both `{"12": …}` and a payload built with an integer key.
     */
    private function validateStringKeys(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $key) {
            if (\is_string($key)) {
                continue;
            }

            $context->buildViolation('This map must be keyed by strings, {{ key }} is not a string key.')
                ->setParameter('{{ key }}', (string) $key)
                ->addViolation();
        }
    }
}
