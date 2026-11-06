<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * Turns a loader's declared config specification, its decoded config object and the element's stored
 * properties into the {@see LoaderInputs} the loader reads. Reference keys are dereferenced and type-checked
 * here, so a loader never guards a stored value itself; an invalid stored value resolves to null rather than
 * throwing.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LoaderInputResolver
{
    /**
     * @param array<string, mixed> $properties the element's stored property map
     */
    public function resolve(
        LoaderConfigSpecification $specification,
        AbstractContentDataLoaderConfig $config,
        array $properties,
    ): LoaderInputs {
        // Public properties only, and unlike jsonSerialize() it never omits a key sitting at its default.
        $configured = get_object_vars($config);

        $values = [];
        foreach ($specification->keys as $key) {
            if (!\array_key_exists($key->name, $configured)) {
                throw ContentSystemException::loaderConfigKeyWithoutProperty($config::class, $key->name);
            }

            $effective = $configured[$key->name] ?? ($key->hasDefault ? $key->default : null);

            $values[$key->name] = $key->kind === ConfigKeyKind::PropertyReference
                ? $this->dereference($key, $effective, $properties)
                : $effective;
        }

        foreach ($specification->keys as $key) {
            if ($key->mergesInto === null) {
                continue;
            }

            $values[$key->mergesInto] = array_merge(
                $this->asStringList($values[$key->mergesInto] ?? null),
                $this->asStringList($values[$key->name] ?? null),
            );
            unset($values[$key->name]);
        }

        return new LoaderInputs($values);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function dereference(ConfigKeySpecification $key, mixed $token, array $properties): mixed
    {
        if (!\is_string($token) || $token === '') {
            return null;
        }

        $value = $properties[$token] ?? null;

        if ($value === null) {
            return null;
        }

        return $this->matchesReferencedType($key->referencedType, $value) ? $value : null;
    }

    private function matchesReferencedType(string $referencedType, mixed $value): bool
    {
        return match ($referencedType) {
            'string' => \is_string($value),
            'list<string>' => \is_array($value) && array_is_list($value) && array_filter($value, 'is_string') === $value,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    private function asStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
