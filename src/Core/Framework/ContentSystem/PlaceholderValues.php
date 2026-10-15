<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\Log\Package;

/**
 * Immutable value object for placeholder values used in content rendering.
 *
 * Replaces the combined EntityIdMap + ParameterMap concept with a simpler flat map.
 * Placeholder replacement doesn't care about the source of values (entity IDs vs URL parameters).
 */
#[Package('framework')]
final readonly class PlaceholderValues
{
    /**
     * @param array<string, string|int|bool|float> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * Keys prefixed `map:` are DROPPED rather than rejected, which is the one place this factory filters instead of
     * validating.
     *
     * The reason is the key source. `Adapter/FactoryHelper/EntityLayoutResolver::resolvePlaceholders()` and the
     * header/footer specification sources all merge every scalar QUERY PARAMETER into this map, so a key here can
     * be attacker-chosen. `Layout/Scaffolding/StoredTreePreparer` then substitutes `{{<key>}}` across every string
     * property before inline mapping expansion runs — so without this filter a request carrying
     * `?map:product.name=…` would pre-empt the inline mapping token `{{map:product.name}}` with its own text, on
     * every element holding one.
     *
     * Throwing would close that door and open a worse one: any visitor could take a page down with a crafted query
     * string. Dropping the key costs nothing legitimate, because no placeholder is named after a mapping path, and
     * it makes the namespace split that {@see InlineMappingTokenParser}'s prefix expresses an enforced invariant
     * rather than a convention.
     *
     * @param array<string, string|int|bool|float> $values
     *
     * @throws ContentSystemException If keys are not strings or values are not scalar
     */
    public static function from(array $values): self
    {
        $admitted = [];

        foreach ($values as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey(
                    'PlaceholderValues',
                    get_debug_type($key)
                );
            }

            if (!\is_scalar($value)) {
                throw ContentSystemException::invalidMapValue(
                    'PlaceholderValues',
                    $key,
                    'scalar',
                    get_debug_type($value)
                );
            }

            if (str_starts_with($key, InlineMappingTokenParser::TOKEN_PREFIX)) {
                continue;
            }

            $admitted[$key] = $value;
        }

        return new self($admitted);
    }

    /**
     * @return array<string, string|int|bool|float>
     */
    public function all(): array
    {
        return $this->values;
    }
}
