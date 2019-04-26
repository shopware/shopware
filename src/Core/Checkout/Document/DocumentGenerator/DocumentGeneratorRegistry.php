<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;

class DocumentGeneratorRegistry
{
    /**
     * @var DocumentGeneratorInterface[]
     */
    protected $documentGenerators;

    public function __construct(iterable $documentGenerators)
    {
        $this->documentGenerators = $documentGenerators;
    }

    public function hasGenerator(string $documentType): bool
    {
        foreach ($this->documentGenerators as $documentGenerator) {
            if ($documentGenerator->supports() !== $documentType) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @throws InvalidDocumentGeneratorTypeException
     */
    public function getGenerator(string $documentType): DocumentGeneratorInterface
    {
        foreach ($this->documentGenerators as $documentGenerator) {
            if ($documentGenerator->supports() !== $documentType) {
                continue;
            }

            return $documentGenerator;
        }

        throw new InvalidDocumentGeneratorTypeException($documentType);
    }

    public function getGenerators(string $documentType): \Generator
    {
        foreach ($this->documentGenerators as $documentGenerator) {
            if ($documentGenerator->supports() !== $documentType) {
                continue;
            }

            yield $documentGenerator;
        }
    }
}
