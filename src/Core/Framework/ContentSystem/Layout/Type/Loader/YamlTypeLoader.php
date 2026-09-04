<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDtoCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Primary element type loader: handles core, bundle, and plugin types in all environments,
 * plus app types in dev (where the compiler pass injects app filesystem directories).
 * In prod, app types are loaded from the database by DatabaseTypeLoader instead.
 *
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
            $resolvedSpecificationDtos = $this->loadDtosFromDirectory($sourceDir->path, $sourceDir->source, $sourceDir->prefix);

            // Cross-directory dedup (within-directory dedup happens in loadDtosFromDirectory)
            foreach ($resolvedSpecificationDtos as $resolvedSpecificationDto) {
                if (isset($seenNames[$resolvedSpecificationDto->name])) {
                    throw ContentSystemException::elementTypeDuplicate(
                        $resolvedSpecificationDto->name,
                        $seenNames[$resolvedSpecificationDto->name],
                        $resolvedSpecificationDto->source
                    );
                }
                $seenNames[$resolvedSpecificationDto->name] = $resolvedSpecificationDto->source;
                $all[] = $resolvedSpecificationDto->toSpecification();
            }
        }

        return $all;
    }

    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    public function loadFromDirectory(string $directory, string $source, string $prefix): array
    {
        return array_map(
            static fn (ResolvedElementTypeSpecificationDto $resolvedSpecificationDto) => $resolvedSpecificationDto->toSpecification(),
            $this->loadDtosFromDirectory($directory, $source, $prefix),
        );
    }

    /**
     * The directory's types keyed by resolved type name: the per-load overlay shape the app binding persister
     * and validator build from an inactive app's own types (not yet in the element-type registry) and thread
     * into binding canonicalization and validation.
     *
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    public function loadOverlayFromDirectory(string $directory, string $source, string $prefix): array
    {
        $overlay = [];
        foreach ($this->loadFromDirectory($directory, $source, $prefix) as $specification) {
            $overlay[$specification->name()] = $specification;
        }

        return $overlay;
    }

    /**
     * Validated and deduplicated within a single directory. Cross-directory
     * deduplication is the caller's responsibility (load() handles it for the standard path).
     *
     * @return list<ResolvedElementTypeSpecificationDto>
     */
    public function loadDtosFromDirectory(string $directory, string $source, string $prefix): array
    {
        $filesystem = new Filesystem($directory);

        if (!$filesystem->has()) {
            return [];
        }

        $files = array_merge(
            $filesystem->findFiles('*.yaml', '.'),
            $filesystem->findFiles('*.yml', '.'),
        );

        if ($files === []) {
            return [];
        }

        $resolvedSpecificationDtos = [];
        $seenNames = [];

        foreach ($files as $fileInfo) {
            $data = $this->parseFile($filesystem, $fileInfo->getRelativePathname());
            $dto = $this->serializer->denormalize($data);

            $name = $this->nameResolver->resolve($fileInfo->getRelativePathname(), $prefix);

            if (isset($seenNames[$name])) {
                throw ContentSystemException::elementTypeDuplicate($name, $seenNames[$name], $fileInfo->getFilename());
            }

            $seenNames[$name] = $fileInfo->getFilename();
            $resolvedSpecificationDtos[] = new ResolvedElementTypeSpecificationDto($name, $source, $dto);
        }

        $specificationDtos = [];
        foreach ($resolvedSpecificationDtos as $resolvedSpecificationDto) {
            $specificationDtos[$resolvedSpecificationDto->name] = $resolvedSpecificationDto->dto;
        }

        $violations = $this->validator->validate(new ElementTypeSpecificationDtoCollection($specificationDtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::elementTypesInvalid($violations);
        }

        return $resolvedSpecificationDtos;
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
