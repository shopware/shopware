<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;

/**
 * Represents the directives of the `No-Vary-Search` header.
 *
 * The header tells clients which parts of the query string may be ignored when matching a request
 * against an already stored response. It is evaluated by the browser HTTP cache and by the
 * prefetch/prerender cache of the Speculation Rules API.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/No-Vary-Search
 *
 * @internal
 *
 * @phpstan-type NoVarySearchDirectivesConfig array{
 *     key_order?: bool|null,
 *     params?: bool|list<string>|null,
 *     except?: list<string>,
 *     include_ignored_url_parameters?: bool
 * }
 */
#[Package('framework')]
readonly class NoVarySearchDirectives
{
    /**
     * Parameter names are serialized into an RFC 8941 string. Restricting them to characters that
     * are valid in a query string and need no escaping guarantees we can never emit a malformed header.
     */
    private const PARAM_NAME_PATTERN = '/^[A-Za-z0-9_.~%+-]+$/';

    /**
     * @param bool|list<string>|null $params `true` marks every parameter as irrelevant, a list marks the given parameters as irrelevant
     * @param list<string> $except Parameters that stay relevant, only allowed in combination with `$params === true`
     */
    public function __construct(
        public ?bool $keyOrder = null,
        public bool|array|null $params = null,
        public array $except = [],
    ) {
        if ($this->except !== [] && $this->params !== true) {
            throw AdapterException::invalidCachePolicyConfiguration(
                'no_vary_search "except" is only allowed in combination with "params: true"'
            );
        }

        foreach ([...(\is_array($this->params) ? $this->params : []), ...$this->except] as $name) {
            if (preg_match(self::PARAM_NAME_PATTERN, $name) !== 1) {
                throw AdapterException::invalidCachePolicyConfiguration(
                    \sprintf('no_vary_search contains the invalid query parameter name "%s"', $name)
                );
            }
        }
    }

    /**
     * Serializes the directives into the value of the `No-Vary-Search` header.
     *
     * Returns `null` when no directive is configured, so callers can skip setting the header at all.
     *
     * @example `key-order, params=("utm_source" "gclid")`
     */
    public function toHeaderValue(): ?string
    {
        $members = [];

        if ($this->keyOrder === true) {
            $members[] = 'key-order';
        }

        if ($this->params === true) {
            $members[] = 'params';

            if ($this->except !== []) {
                $members[] = \sprintf('except=(%s)', $this->quote($this->except));
            }
        } elseif (\is_array($this->params) && $this->params !== []) {
            $members[] = \sprintf('params=(%s)', $this->quote($this->params));
        }

        if ($members === []) {
            return null;
        }

        return implode(', ', $members);
    }

    /**
     * @return NoVarySearchDirectivesConfig
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->keyOrder !== null) {
            $data['key_order'] = $this->keyOrder;
        }

        if ($this->params !== null) {
            $data['params'] = $this->params;
        }

        if ($this->except !== []) {
            $data['except'] = $this->except;
        }

        return $data;
    }

    /**
     * Create from configuration array.
     *
     * `include_ignored_url_parameters` is not evaluated here, it is resolved into `params` by
     * {@see CachePolicyProviderFactory} which has access to the globally configured parameter list.
     *
     * @param NoVarySearchDirectivesConfig $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            keyOrder: isset($data['key_order']) ? (bool) $data['key_order'] : null,
            params: self::readParams($data['params'] ?? null),
            except: self::readList($data['except'] ?? [], 'except'),
        );
    }

    /**
     * Create a new instance with overridden values.
     *
     * @param NoVarySearchDirectivesConfig $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /**
     * @return bool|list<string>|null
     */
    private static function readParams(mixed $params): bool|array|null
    {
        if ($params === null || \is_bool($params)) {
            return $params;
        }

        return self::readList($params, 'params');
    }

    /**
     * @return list<string>
     */
    private static function readList(mixed $values, string $key): array
    {
        if (!\is_array($values) || !array_is_list($values)) {
            throw AdapterException::invalidCachePolicyConfiguration(
                \sprintf('no_vary_search "%s" must be a list of query parameter names', $key)
            );
        }

        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw AdapterException::invalidCachePolicyConfiguration(
                    \sprintf('no_vary_search "%s" must only contain strings', $key)
                );
            }
        }

        /** @var list<string> $values */
        return $values;
    }

    /**
     * @param list<string> $names
     */
    private function quote(array $names): string
    {
        return implode(' ', array_map(static fn (string $name): string => '"' . $name . '"', $names));
    }
}
