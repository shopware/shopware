<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
final class YamlTypeLoader extends AbstractContentElementTypeLoader
{
    public function __construct(
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
        private readonly string $directory,
    ) {
    }

    /**
     * @return list<ContentElementTypeSpecification>
     */
    public function load(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = glob($this->directory . '/*.yaml');

        if ($files === false || $files === []) {
            return [];
        }

        $definitions = [];
        $seenNames = [];

        foreach ($files as $file) {
            $data = $this->parseFile($file);
            $dto = $this->serializer->denormalize($data);

            $violations = $this->validator->validate($dto);
            if ($violations->count() > 0) {
                throw ContentSystemException::elementTypeInvalid(
                    $dto->name ?: '<unknown>',
                    $this->formatViolations($violations)
                );
            }

            $name = $dto->name;

            if (isset($seenNames[$name])) {
                throw ContentSystemException::elementTypeDuplicate($name, basename($seenNames[$name]), basename($file));
            }

            $seenNames[$name] = $file;
            $definitions[] = $dto->toContentElementTypeSpecification();
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(string $file): array
    {
        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $e) {
            throw ContentSystemException::elementTypeLoadFailed($file, 'Invalid YAML syntax: ' . $e->getMessage(), $e);
        }

        if (!\is_array($data)) {
            throw ContentSystemException::elementTypeLoadFailed($file, 'YAML file must contain an array/map, got ' . get_debug_type($data));
        }

        return $data;
    }

    private function formatViolations(ConstraintViolationListInterface $violations): string
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return implode('; ', $messages);
    }
}
