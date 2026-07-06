<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory;
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
class YamlBindingSpecificationLoader extends AbstractContentSystemBindingSpecificationLoader
{
    // Matches the `name` column of `app_content_system_binding_specification`
    // (Migration1782423128AddAppContentSystemBindingSpecificationTable), the persistence target every id
    // resolved here eventually reaches (core/bundle/plugin bindings never persist, but app bindings do).
    private const MAX_ID_LENGTH = 255;

    /**
     * @param list<ElementTypeSourceDirectory> $directories
     */
    public function __construct(
        private readonly array $directories,
        private readonly BindingSpecificationSerializer $serializer,
        private readonly BindingSpecificationCanonicalizer $canonicalizer,
        private readonly ValidatorInterface $validator,
        private readonly ElementTypeNameResolver $nameResolver,
    ) {
    }

    /**
     * @return list<BindingSpecification>
     */
    public function load(): array
    {
        $all = [];
        $seenQualifiedIds = [];
        $promotedByType = [];

        foreach ($this->directories as $sourceDir) {
            $resolvedSpecificationDtos = $this->loadDtosFromTypeDirectory($sourceDir->path, $sourceDir->source, $sourceDir->prefix);

            // Cross-directory dedup is by source-qualified id, so two sources can each ship the same bare
            // id (within-directory dedup by bare id happens in loadDtosFromTypeDirectory).
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

                $specification = $resolvedSpecificationDto->toSpecification();

                // Promoted uniqueness is per element type across ALL of this loader's directories and sources:
                // two promoted specifications for one type is an authored bug, hard by design. The built
                // specification carries the type; the raw dto only carries it untyped.
                if ($specification->isPromoted()) {
                    $type = $specification->type();

                    if (isset($promotedByType[$type])) {
                        throw ContentSystemException::bindingSpecificationPromotedDuplicate($type, $promotedByType[$type], $qualifiedId);
                    }

                    $promotedByType[$type] = $qualifiedId;
                }

                $all[] = $specification;
            }
        }

        return $all;
    }

    /**
     * Validated and deduplicated within a single element-type directory (by bare id). Scans each `*.yaml`/`*.yml`
     * file, reads the optional top-level `bindings` map, and loads each entry as a specification whose type is
     * implicit, resolved from the file path plus the directory prefix via the same {@see ElementTypeNameResolver}
     * the type loader uses. Files without a `bindings` key are skipped.
     *
     * @param array<string, ContentSystemElementTypeSpecification> $typeOverlay type-name → spec for types not yet
     *                                                                          in the registry; consulted before the registry so an app's own inline binding resolves against its own
     *                                                                          co-loaded type at install/validate time, empty for every non-app path
     *
     * @return list<ResolvedBindingSpecificationDto>
     */
    public function loadDtosFromTypeDirectory(string $directory, string $source, string $prefix, array $typeOverlay = []): array
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
            $relativePath = $fileInfo->getRelativePathname();
            $data = $this->parseFile($filesystem, $relativePath);

            $bindings = $data['bindings'] ?? null;
            if ($bindings === null) {
                continue;
            }

            $path = $filesystem->path($relativePath);
            if (!\is_array($bindings)) {
                throw ContentSystemException::bindingSpecificationLoadFailed($path, 'the "bindings" section must be a map of specification id to entry, got ' . get_debug_type($bindings));
            }

            $implicitType = $this->nameResolver->resolve($relativePath, $prefix);

            foreach ($bindings as $bareId => $entryData) {
                $id = $this->assertValidId($bareId, $path);

                // Duplicate detection runs before any per-entry processing, so a duplicate id surfaces as the
                // duplicate error even when the second entry would also fail shape checks or canonicalization.
                if (isset($seenIds[$id])) {
                    throw ContentSystemException::bindingSpecificationDuplicate($id, $seenIds[$id], $fileInfo->getFilename());
                }

                $seenIds[$id] = $fileInfo->getFilename();

                if (!\is_array($entryData)) {
                    throw ContentSystemException::bindingSpecificationLoadFailed($path, \sprintf('the "bindings" entry "%s" must be a map, got %s', $id, get_debug_type($entryData)));
                }

                $this->rejectAuthoredTypeOrId($entryData, $id);

                // The implicit type is injected; every other facet is authored inline and passes through
                // unchanged into the denormalize path. Keys are string-cast because a map value carries
                // array-key keys and the serializer reads named keys.
                $specificationData = ['type' => $implicitType];
                foreach ($entryData as $facetKey => $facetValue) {
                    $specificationData[(string) $facetKey] = $facetValue;
                }

                $dto = $this->canonicalizer->canonicalize($this->serializer->denormalize($specificationData), $id, $typeOverlay);

                $resolvedSpecificationDtos[] = new ResolvedBindingSpecificationDto($id, $source, $dto);
            }
        }

        $specificationDtos = [];
        foreach ($resolvedSpecificationDtos as $resolvedSpecificationDto) {
            $specificationDtos[$resolvedSpecificationDto->id] = $resolvedSpecificationDto->dto;
        }

        $violations = $this->validator->validate(new BindingSpecificationDtoCollection($specificationDtos, $typeOverlay));
        if ($violations->count() > 0) {
            throw ContentSystemException::bindingSpecificationsInvalid($violations);
        }

        return $resolvedSpecificationDtos;
    }

    private function assertValidId(mixed $id, string $path): string
    {
        if (!\is_string($id) || $id === '') {
            throw ContentSystemException::bindingSpecificationLoadFailed($path, 'missing or empty "id"');
        }

        if (\strlen($id) > self::MAX_ID_LENGTH) {
            throw ContentSystemException::bindingSpecificationLoadFailed($path, \sprintf('id exceeds the maximum length of %d characters', self::MAX_ID_LENGTH));
        }

        return $id;
    }

    /**
     * An inline entry's type is implicit (the containing file's type) and its id is the map key. An authored
     * `type` or `id` inside the entry would silently drift from those, so both are hard load-time errors. Runs
     * before denormalization so the guard is never bypassed by the `['type' => …] + $entry`
     * union that would otherwise let an inner `type` be overridden and an inner `id` be dropped unnoticed.
     *
     * @param array<array-key, mixed> $entryData
     */
    private function rejectAuthoredTypeOrId(array $entryData, string $id): void
    {
        if (\array_key_exists('type', $entryData)) {
            throw ContentSystemException::bindingSpecificationCanonicalizationFailed($id, 'an inline binding entry must not declare "type"; the type is implicit from the containing element-type file.');
        }

        if (\array_key_exists('id', $entryData)) {
            throw ContentSystemException::bindingSpecificationCanonicalizationFailed($id, 'an inline binding entry must not declare "id"; the map key is the id.');
        }
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
