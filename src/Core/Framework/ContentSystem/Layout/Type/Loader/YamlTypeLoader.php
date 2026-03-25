<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
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
class YamlTypeLoader extends AbstractContentSystemElementTypeLoader
{
    /**
     * @param list<ElementTypeSourceDirectory> $directories
     */
    public function __construct(
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly ElementTypeNameResolver $nameResolver,
        private readonly array $directories = [],
    ) {
    }

    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    public function load(): array
    {
        $all = [];
        $seenNames = [];

        foreach ($this->directories as $sourceDir) {
            $dtos = $this->loadDtosFromDirectory($sourceDir->path, $sourceDir->source, $sourceDir->prefix);

            foreach ($dtos as $resolved) {
                if (isset($seenNames[$resolved->name])) {
                    throw ContentSystemException::elementTypeDuplicate(
                        $resolved->name,
                        $seenNames[$resolved->name],
                        $resolved->source
                    );
                }
                $seenNames[$resolved->name] = $resolved->source;
                $all[] = $resolved->toSpecification();
            }
        }

        return $all;
    }

    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    public function loadFromDirectory(string $directory, string $source, string $prefix): array
    {
        return $this->loadBatchFromDirectory($directory, $source, $prefix)->toSpecifications();
    }

    /**
     * @return list<ResolvedElementTypeSpecificationDto>
     */
    public function loadDtosFromDirectory(string $directory, string $source, string $prefix): array
    {
        return $this->loadBatchFromDirectory($directory, $source, $prefix)->items;
    }

    public function loadBatchFromDirectory(string $directory, string $source, string $prefix): ResolvedElementTypeSpecificationDtoCollection
    {
        $filesystem = new Filesystem($directory);

        if (!$filesystem->has()) {
            return new ResolvedElementTypeSpecificationDtoCollection([]);
        }

        $files = array_merge(
            $filesystem->findFiles('*.yaml', '.'),
            $filesystem->findFiles('*.yml', '.'),
        );

        if ($files === []) {
            return new ResolvedElementTypeSpecificationDtoCollection([]);
        }

        $resolved = [];
        $seenNames = [];

        foreach ($files as $fileInfo) {
            $data = $this->parseFile($filesystem, $fileInfo->getRelativePathname());
            $dto = $this->serializer->denormalize($data);

            $name = $this->nameResolver->resolve($fileInfo->getRelativePathname(), $prefix);

            if (isset($seenNames[$name])) {
                throw ContentSystemException::elementTypeDuplicate($name, $seenNames[$name], $fileInfo->getFilename());
            }

            $seenNames[$name] = $fileInfo->getFilename();
            $resolved[] = new ResolvedElementTypeSpecificationDto($name, $source, $dto);
        }

        $batch = new ResolvedElementTypeSpecificationDtoCollection($resolved);
        $batch->validate($this->validator);

        return $batch;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(Filesystem $filesystem, string $relativePath): array
    {
        $content = $filesystem->read($relativePath);

        try {
            $data = Yaml::parse($content);
        } catch (ParseException $e) {
            throw ContentSystemException::elementTypeLoadFailed($filesystem->path($relativePath), 'Invalid YAML syntax: ' . $e->getMessage(), $e);
        }

        if (!\is_array($data)) {
            throw ContentSystemException::elementTypeLoadFailed($filesystem->path($relativePath), 'YAML file must contain an array/map, got ' . get_debug_type($data));
        }

        return $data;
    }
}
