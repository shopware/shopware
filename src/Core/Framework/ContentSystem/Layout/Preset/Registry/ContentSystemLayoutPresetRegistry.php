<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPreset;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemLayoutPresetRegistry extends AbstractContentSystemLayoutPresetRegistry
{
    private const DEFINITIONS_DIRECTORY = __DIR__ . '/../Definitions';

    public function __construct(
        private readonly LayoutPresetPayloadCompiler $compiler,
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

        $files = array_merge(
            $filesystem->findFiles('*.yaml', '.'),
            $filesystem->findFiles('*.yml', '.'),
        );

        $presets = [];

        foreach ($files as $fileInfo) {
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
            $data = Yaml::parse($filesystem->read($relativePath));
        } catch (ParseException $e) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'Invalid YAML syntax: ' . $e->getMessage(), $e);
        }

        if (!\is_array($data)) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'File must contain a YAML mapping, got ' . get_debug_type($data));
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

        $layout = $data['layout'] ?? null;
        if (!\is_array($layout) || !array_is_list($layout)) {
            throw ContentSystemException::layoutPresetLoadFailed($path, 'The "layout" field is required and must be a list of elements.');
        }

        try {
            $payload = $this->compiler->compile($layout);
        } catch (ContentSystemException $e) {
            throw ContentSystemException::layoutPresetLoadFailed($path, $e->getMessage(), $e);
        }

        return new LayoutPreset($id, $name, $description, $icon, $payload);
    }
}
