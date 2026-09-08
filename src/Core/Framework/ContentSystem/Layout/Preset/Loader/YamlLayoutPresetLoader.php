<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class YamlLayoutPresetLoader extends AbstractContentSystemLayoutPresetLoader
{
    /**
     * @param list<LayoutPresetSourceDirectory> $directories
     */
    public function __construct(
        private readonly LayoutPresetSerializer $serializer,
        private readonly LayoutPresetNameResolver $nameResolver,
        private readonly array $directories = [],
    ) {
    }

    /**
     * @return list<ContentSystemLayoutPresetSpecification>
     */
    public function load(): array
    {
        $all = [];
        $seen = [];

        foreach ($this->directories as $directory) {
            foreach ($this->loadFromDirectory($directory->path, $directory->source, $directory->prefix) as $preset) {
                if (isset($seen[$preset->id])) {
                    throw ContentSystemException::layoutPresetDuplicate($preset->id);
                }

                $seen[$preset->id] = true;
                $all[] = $preset;
            }
        }

        return $all;
    }

    /**
     * @return list<ContentSystemLayoutPresetSpecification>
     */
    public function loadFromDirectory(string $directory, string $source, string $prefix): array
    {
        $presets = [];
        $seen = [];

        foreach ($this->parsedFiles($directory) as [$relativePath, $path, $data]) {
            $id = $this->nameResolver->resolve($relativePath, $prefix);

            try {
                $preset = $this->serializer->denormalize($data, $id);
            } catch (ContentSystemException $e) {
                throw ContentSystemException::layoutPresetLoadFailed($path, $e->getMessage(), $e);
            }

            if (isset($seen[$id])) {
                throw ContentSystemException::layoutPresetDuplicate($id);
            }

            $seen[$id] = true;
            $presets[] = $preset;
        }

        return $presets;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function readRawFromDirectory(string $directory, string $source, string $prefix): array
    {
        $raw = [];

        foreach ($this->parsedFiles($directory) as [$relativePath, $path, $data]) {
            try {
                $this->serializer->validate($data);
            } catch (ContentSystemException $e) {
                throw ContentSystemException::layoutPresetLoadFailed($path, $e->getMessage(), $e);
            }

            $id = $this->nameResolver->resolve($relativePath, $prefix);

            if (isset($raw[$id])) {
                throw ContentSystemException::layoutPresetDuplicate($id);
            }

            $raw[$id] = $data;
        }

        return $raw;
    }

    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function parsedFiles(string $directory): array
    {
        $filesystem = new Filesystem($directory);

        if (!$filesystem->has()) {
            return [];
        }

        $files = array_merge(
            $filesystem->findFiles('*.yaml', '.'),
            $filesystem->findFiles('*.yml', '.'),
        );

        $parsed = [];

        foreach ($files as $fileInfo) {
            $relativePath = $fileInfo->getRelativePathname();
            $path = $filesystem->path($relativePath);

            try {
                $data = Yaml::parse($filesystem->read($relativePath));
            } catch (ParseException $e) {
                throw ContentSystemException::layoutPresetLoadFailed($path, 'Invalid YAML syntax: ' . $e->getMessage(), $e);
            }

            if (!\is_array($data)) {
                throw ContentSystemException::layoutPresetLoadFailed($path, 'File must contain a YAML mapping, got ' . get_debug_type($data));
            }

            $parsed[] = [$relativePath, $path, $data];
        }

        return $parsed;
    }
}
