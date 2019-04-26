<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException;

class FileGeneratorRegistry
{
    /**
     * @var FileGeneratorInterface[]
     */
    private $fileGenerators;

    public function __construct(iterable $fileGenerators)
    {
        $this->fileGenerators = $fileGenerators;
    }

    public function hasGenerator(string $fileType): bool
    {
        foreach ($this->fileGenerators as $fileGenerator) {
            if ($fileGenerator->supports() !== $fileType) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @throws InvalidFileGeneratorTypeException
     */
    public function getGenerator(string $fileType): FileGeneratorInterface
    {
        foreach ($this->fileGenerators as $fileGenerator) {
            if ($fileGenerator->supports() !== $fileType) {
                continue;
            }

            return $fileGenerator;
        }

        throw new InvalidFileGeneratorTypeException($fileType);
    }

    public function getGenerators(string $fileType): \Generator
    {
        foreach ($this->fileGenerators as $fileGenerator) {
            if ($fileGenerator->supports() !== $fileType) {
                continue;
            }

            yield $fileGenerator;
        }
    }
}
