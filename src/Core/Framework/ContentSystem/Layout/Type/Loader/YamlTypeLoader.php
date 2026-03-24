<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
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
class YamlTypeLoader
{
    public function __construct(
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<ContentElementTypeSpecification>
     */
    public function load(Filesystem $filesystem): array
    {
        if (!$filesystem->has()) {
            return [];
        }

        $files = $filesystem->findFiles('*.yaml', '.');

        if ($files === []) {
            return [];
        }

        $definitions = [];
        $seenNames = [];

        foreach ($files as $fileInfo) {
            $data = $this->parseFile($filesystem, $fileInfo->getRelativePathname());
            $dto = $this->serializer->denormalize($data);

            $violations = $this->validator->validate($dto);
            if ($violations->count() > 0) {
                throw ContentSystemException::elementTypeInvalid(
                    $dto->name ?: '<unknown>',
                    $violations
                );
            }

            $name = $dto->name;

            if (isset($seenNames[$name])) {
                throw ContentSystemException::elementTypeDuplicate($name, $seenNames[$name], $fileInfo->getFilename());
            }

            $seenNames[$name] = $fileInfo->getFilename();
            $definitions[] = $dto->toContentElementTypeSpecification();
        }

        return $definitions;
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
