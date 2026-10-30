<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDtoCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
        private readonly LayoutPresetSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly LayoutPresetNameResolver $nameResolver,
        private readonly array $directories = [],
    ) {
    }

    /**
     * @return array<string, LayoutPresetSpecificationDto>
     */
    public function load(): array
    {
        $all = [];

        foreach ($this->directories as $directory) {
            foreach ($this->loadDtosFromDirectory($directory->path, $directory->source, $directory->prefix) as $id => $dto) {
                if (isset($all[$id])) {
                    throw ContentSystemException::layoutPresetDuplicate($id);
                }

                $all[$id] = $dto;
            }
        }

        return $all;
    }

    /**
     * The directory's presets keyed by resolved id, denormalized and validated but not compiled — the shape the
     * app preset persister stores and the app validator checks.
     *
     * @return array<string, LayoutPresetSpecificationDto>
     */
    public function loadDtosFromDirectory(string $directory, string $source, string $prefix): array
    {
        $dtos = [];

        foreach ($this->parsedFiles($directory) as [$relativePath, $data]) {
            $id = $this->nameResolver->resolve($relativePath, $prefix);

            if (isset($dtos[$id])) {
                throw ContentSystemException::layoutPresetDuplicate($id);
            }

            $dtos[$id] = $this->serializer->denormalize($data);
        }

        $violations = $this->validator->validate(new LayoutPresetSpecificationDtoCollection($dtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::layoutPresetsInvalid($violations);
        }

        return $dtos;
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}>
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

            $parsed[] = [$relativePath, $data];
        }

        return $parsed;
    }
}
