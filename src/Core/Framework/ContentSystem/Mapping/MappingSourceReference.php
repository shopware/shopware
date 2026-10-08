<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * Identifies a typed source and an optional member within it.
 *
 * Root sources use the layout root-context key as `id` and a safe member path as `path`. Loader and context
 * providers use their registered source id and may carry provider-validated configuration.
 */
#[Package('framework')]
final readonly class MappingSourceReference implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $type,
        public string $id,
        public array $config = [],
        public ?string $path = null,
    ) {
    }

    public static function root(string $contextKey, ?string $path = null): self
    {
        return new self('root', $contextKey, path: $path);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $fieldPath): self
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, ['type', 'id', 'config', 'path'], true)) {
                throw ContentSystemException::invalidFieldValueType($fieldPath, 'source fields type, id, config, path', 'unknown key ' . $key);
            }
        }

        if (!\is_string($data['type'] ?? null) || $data['type'] === '') {
            throw ContentSystemException::invalidFieldValueType($fieldPath . '.type', 'non-empty string', get_debug_type($data['type'] ?? null));
        }

        if (!\is_string($data['id'] ?? null) || $data['id'] === '') {
            throw ContentSystemException::invalidFieldValueType($fieldPath . '.id', 'non-empty string', get_debug_type($data['id'] ?? null));
        }

        $config = $data['config'] ?? [];
        if (!\is_array($config)) {
            throw ContentSystemException::invalidFieldValueType($fieldPath . '.config', 'array', get_debug_type($config));
        }

        $path = $data['path'] ?? null;
        if ($path !== null && (!\is_string($path) || $path === '')) {
            throw ContentSystemException::invalidFieldValueType($fieldPath . '.path', 'non-empty string', get_debug_type($path));
        }

        return new self($data['type'], $data['id'], $config, $path);
    }

    public function displayName(): string
    {
        $sourceName = $this->type === 'root' ? $this->id : $this->type . ':' . $this->id;

        return $this->path === null ? $sourceName : $sourceName . '.' . $this->path;
    }

    public function isSameAs(self $other): bool
    {
        return $this->jsonSerialize() === $other->jsonSerialize();
    }

    /**
     * Build the typed root reference represented by an existing catalogued root path.
     */
    public static function fromRootPath(string $path): self
    {
        [$contextKey, $memberPath] = explode('.', $path, 2);

        return self::root($contextKey, $memberPath);
    }

    /**
     * @return array{type: string, id: string, config?: array<string, mixed>, path?: string}
     */
    public function jsonSerialize(): array
    {
        $source = ['type' => $this->type, 'id' => $this->id];

        if ($this->config !== []) {
            $source['config'] = $this->config;
        }

        if ($this->path !== null) {
            $source['path'] = $this->path;
        }

        return $source;
    }
}
