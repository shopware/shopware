<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\ContentSystem\Binding\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Primary binding specification loader: handles core, bundle, and plugin bindings in all environments,
 * plus app bindings in dev (where the compiler pass injects app filesystem directories).
 * In prod, app bindings are loaded from the database by DatabaseBindingSpecificationLoader instead.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class YamlBindingSpecificationLoader extends AbstractContentSystemBindingSpecificationLoader
{
    /**
     * @param list<BindingSpecificationSourceDirectory> $directories
     */
    public function __construct(
        private readonly BindingSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly array $directories = [],
    ) {
    }

    /**
     * @return list<BindingSpecification>
     */
    public function load(): array
    {
        $all = [];
        $seenQualifiedIds = [];

        foreach ($this->directories as $sourceDir) {
            $resolvedSpecificationDtos = $this->loadDtosFromDirectory($sourceDir->path, $sourceDir->source, $sourceDir->prefix);

            // Cross-directory dedup is by source-qualified id, so two sources can each ship the same bare
            // id (within-directory dedup by bare id happens in loadDtosFromDirectory).
            foreach ($resolvedSpecificationDtos as $resolvedSpecificationDto) {
                $qualifiedId = $resolvedSpecificationDto->source . ':' . $resolvedSpecificationDto->id;

                if (isset($seenQualifiedIds[$qualifiedId])) {
                    throw ContentSystemException::bindingSpecificationDuplicate(
                        $resolvedSpecificationDto->id,
                        $seenQualifiedIds[$qualifiedId],
                        $resolvedSpecificationDto->source
                    );
                }
                $seenQualifiedIds[$qualifiedId] = $resolvedSpecificationDto->source;
                $all[] = $resolvedSpecificationDto->toSpecification();
            }
        }

        return $all;
    }

    /**
     * @return list<BindingSpecification>
     */
    public function loadFromDirectory(string $directory, string $source, string $prefix): array
    {
        return array_map(
            static fn (ResolvedBindingSpecificationDto $resolvedSpecificationDto) => $resolvedSpecificationDto->toSpecification(),
            $this->loadDtosFromDirectory($directory, $source, $prefix),
        );
    }

    /**
     * Validated and deduplicated within a single directory (by bare id). Cross-directory deduplication
     * (by source-qualified id) is the caller's responsibility (load() handles it for the standard path).
     *
     * $prefix is unused here (bindings have no path-derived name) and accepted only for
     * signature-parallelism with the app persister's call.
     *
     * @return list<ResolvedBindingSpecificationDto>
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
        $seenIds = [];

        foreach ($files as $fileInfo) {
            $data = $this->parseFile($filesystem, $fileInfo->getRelativePathname());
            $id = $this->resolveId($data, $filesystem->path($fileInfo->getRelativePathname()));
            $dto = $this->serializer->denormalize($data);

            if (isset($seenIds[$id])) {
                throw ContentSystemException::bindingSpecificationDuplicate($id, $seenIds[$id], $fileInfo->getFilename());
            }

            $seenIds[$id] = $fileInfo->getFilename();
            $resolvedSpecificationDtos[] = new ResolvedBindingSpecificationDto($id, $source, $dto);
        }

        $specificationDtos = [];
        foreach ($resolvedSpecificationDtos as $resolvedSpecificationDto) {
            $specificationDtos[$resolvedSpecificationDto->id] = $resolvedSpecificationDto->dto;
        }

        $violations = $this->validator->validate(new BindingSpecificationDtoCollection($specificationDtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::bindingSpecificationsInvalid($violations);
        }

        return $resolvedSpecificationDtos;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveId(array $data, string $path): string
    {
        $id = $data['id'] ?? null;

        if (!\is_string($id) || $id === '') {
            throw ContentSystemException::bindingSpecificationLoadFailed($path, 'missing or empty "id"');
        }

        return $id;
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
            throw ContentSystemException::bindingSpecificationLoadFailed($filesystem->path($relativePath), 'Invalid YAML syntax: ' . $e->getMessage(), $e);
        }

        if (!\is_array($data)) {
            throw ContentSystemException::bindingSpecificationLoadFailed($filesystem->path($relativePath), 'YAML file must contain an array/map, got ' . get_debug_type($data));
        }

        return $data;
    }
}
