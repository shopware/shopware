<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPreset;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * Loads the layout presets shipped with the core from the JSON wrapper files in the Definitions directory, keyed
 * by their author-provided root-level "id". A preset is a ready-made layout fragment the admin can drop into the
 * layout it is editing; the payload is a complete or partial layout tree (the same wire shape as a saved layout).
 *
 * The file shape is a thin wrapper around a layout tree:
 *
 *     { "id": "...", "name": "...", "description": "...", "icon": "...", "payload": [ <encoded elements> ] }
 *
 * Each preset's payload is run through the shared {@see DraftLayoutDecoder} — the same structural gate the
 * diagnose/preview/mutation actions use — so a malformed preset fails hard here rather than reaching the admin.
 * This uncached work is memoized by {@see CachedContentSystemLayoutPresetRegistry}.
 *
 * @internal
 */
#[Package('framework')]
class ContentSystemLayoutPresetRegistry extends AbstractContentSystemLayoutPresetRegistry
{
    private const DEFINITIONS_DIRECTORY = __DIR__ . '/../Definitions';

    public function __construct(
        private readonly DraftLayoutDecoder $decoder,
        private readonly string $definitionsDirectory = self::DEFINITIONS_DIRECTORY,
    ) {
    }

    public function getDecorated(): AbstractContentSystemLayoutPresetRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, LayoutPreset>
     */
    public function all(): array
    {
        $filesystem = new Filesystem($this->definitionsDirectory);

        if (!$filesystem->has()) {
            return [];
        }

        $presets = [];

        foreach ($filesystem->findFiles('*.json', '.') as $fileInfo) {
            $preset = $this->parse($filesystem, $fileInfo->getRelativePathname());

            if (isset($presets[$preset->id])) {
                throw ContentSystemException::layoutPresetDuplicate($preset->id);
            }

            $presets[$preset->id] = $preset;
        }

        return $presets;
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->all());
    }

    public function get(string $id): LayoutPreset
    {
        return $this->all()[$id] ?? throw ContentSystemException::layoutPresetNotFound($id);
    }

    private function parse(Filesystem $filesystem, string $relativePath): LayoutPreset
    {
        $path = $filesystem->path($relativePath);

        try {
            $data = json_decode($filesystem->read($relativePath), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'Invalid JSON syntax: ' . $e->getMessage(), $e);
        }

        if (!\is_array($data)) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'File must contain a JSON object, got ' . get_debug_type($data));
        }

        $id = $data['id'] ?? null;
        if (!\is_string($id) || $id === '') {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'The "id" field is required and must be a non-empty string.');
        }

        $name = $data['name'] ?? null;
        if (!\is_string($name) || $name === '') {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'The "name" field is required and must be a non-empty string.');
        }

        $description = $data['description'] ?? null;
        if ($description !== null && !\is_string($description)) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'The "description" field must be a string.');
        }

        $icon = $data['icon'] ?? null;
        if ($icon !== null && !\is_string($icon)) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'The "icon" field must be a string.');
        }

        $payload = $data['payload'] ?? null;
        if (!\is_array($payload) || !array_is_list($payload)) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'The "payload" field is required and must be a list of elements.');
        }

        $this->assertValidPayload($path, $payload);

        /** @var list<array<string, mixed>> $payload */
        return new LayoutPreset($id, $name, $description, $icon, $payload);
    }

    /**
     * Validates the payload through the shared structural decode gate (element well-formedness, decodable configs,
     * globally unique element ids). A defect in a core preset is a server misconfiguration, so it fails hard.
     *
     * @param list<mixed> $payload
     */
    private function assertValidPayload(string $path, array $payload): void
    {
        try {
            $this->decoder->decode($payload);
        } catch (ContentSystemException $e) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'Invalid payload: ' . $e->getMessage(), $e);
        }
    }
}
