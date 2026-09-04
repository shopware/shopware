<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Filesystem loader for core, bundle, and plugin style options, plus app options in dev (the prod
 * app path is DatabaseStyleOptionLoader). The option name is the kebab-case filename.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class YamlStyleOptionLoader extends AbstractContentSystemStyleOptionLoader
{
    // Must start with a letter: an all-numeric name (e.g. "123") would be coerced to an int array key on
    // read and silently dropped by the registry-free deserialize, so it could never round-trip.
    private const NAME_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * @param list<StyleOptionSourceDirectory> $directories
     */
    public function __construct(
        private readonly StyleOptionSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly array $directories = [],
    ) {
    }

    /**
     * @return list<StyleOptionSpecification>
     */
    public function load(): array
    {
        $all = [];
        $seenNames = [];

        foreach ($this->directories as $sourceDir) {
            $resolved = $this->loadDtosFromDirectory($sourceDir->path, $sourceDir->source);

            // Cross-directory dedup (within-directory dedup happens in loadDtosFromDirectory)
            foreach ($resolved as $resolvedDto) {
                if (isset($seenNames[$resolvedDto->name])) {
                    throw ContentSystemException::styleOptionDuplicate(
                        $resolvedDto->name,
                        $seenNames[$resolvedDto->name],
                        $resolvedDto->source,
                    );
                }

                $seenNames[$resolvedDto->name] = $resolvedDto->source;
                $all[] = $resolvedDto->toSpecification();
            }
        }

        return $all;
    }

    /**
     * Validated and deduplicated within a single directory. Cross-directory deduplication is the
     * caller's responsibility (load() handles it for the standard path).
     *
     * @return list<ResolvedStyleOptionSpecificationDto>
     */
    public function loadDtosFromDirectory(string $directory, string $source): array
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

        $resolved = [];
        $seenNames = [];

        foreach ($files as $fileInfo) {
            $data = $this->parseFile($filesystem, $fileInfo->getRelativePathname());
            $dto = $this->serializer->denormalize($data);
            $name = $this->resolveName($fileInfo->getFilename());

            if (isset($seenNames[$name])) {
                throw ContentSystemException::styleOptionDuplicate($name, $seenNames[$name], $fileInfo->getFilename());
            }

            $seenNames[$name] = $fileInfo->getFilename();
            $resolved[] = new ResolvedStyleOptionSpecificationDto($name, $source, $dto);
        }

        $dtos = [];
        foreach ($resolved as $resolvedDto) {
            $dtos[$resolvedDto->name] = $resolvedDto->dto;
        }

        $violations = $this->validator->validate(new StyleOptionSpecificationDtoCollection($dtos));
        if ($violations->count() > 0) {
            throw ContentSystemException::styleOptionsInvalid($violations);
        }

        return $resolved;
    }

    private function resolveName(string $filename): string
    {
        $name = pathinfo($filename, \PATHINFO_FILENAME);

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw ContentSystemException::styleOptionInvalidFilename($name, $filename);
        }

        return $name;
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
            throw ContentSystemException::styleOptionLoadFailed($filesystem->path($relativePath), 'Invalid YAML syntax: ' . $e->getMessage(), $e);
        }

        if (!\is_array($data)) {
            throw ContentSystemException::styleOptionLoadFailed($filesystem->path($relativePath), 'YAML file must contain an array/map, got ' . get_debug_type($data));
        }

        return $data;
    }
}
