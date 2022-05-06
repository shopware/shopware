<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Will be removed
 */
class DocumentGeneratorRegistry
{
    /**
     * @var DocumentGeneratorInterface[]
     */
    protected $documentGenerators;

    /**
     * @internal
     */
    public function __construct(iterable $documentGenerators)
    {
        $this->documentGenerators = $documentGenerators;
    }

    public function hasGenerator(string $documentType): bool
    {
        Feature::throwException('v6.5.0.0', 'Will be removed');

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
        Feature::throwException('v6.5.0.0', 'Will be removed');

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
        Feature::throwException('v6.5.0.0', 'Will be removed');

        foreach ($this->documentGenerators as $documentGenerator) {
            if ($documentGenerator->supports() !== $documentType) {
                continue;
            }

            yield $documentGenerator;
        }
    }
}
