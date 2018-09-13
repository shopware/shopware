<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\Type\DocumentMetadata;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;
use Smalot\PdfParser\Parser;

class PdfParserLoader implements MetadataLoaderInterface
{
    /**
     * @var Parser
     */
    private $pdfParser;

    /**
     * @param string $filePath
     *
     * @return array
     */
    public function extractMetadata(string $filePath): array
    {
        try {
            $document = $this->getPdfParser()
                ->parseFile($filePath);
        } catch (\Exception $e) {
            ob_end_clean(); // fixes a library bug

            throw new CanNotLoadMetadataException(sprintf('File %s is not supported by library pdfparser', $filePath), 0, $e);
        }

        $metadata = $document->getDetails();

        if (isset($metadata['error'])) {
            throw new CanNotLoadMetadataException(sprintf('File %s is not supported by library pdfparser', $filePath));
        }

        return $metadata;
    }

    public function enhanceTypeObject(MetadataType $metadataType, array $rawMetadata): void
    {
        if (!$metadataType instanceof DocumentMetadata) {
            return;
        }

        if (isset($rawMetadata['Pages'])) {
            $metadataType->setPages($rawMetadata['Pages']);
        } else {
            $metadataType->setPages(0);
        }

        if (isset($rawMetadata['Creator'])) {
            $metadataType->setCreator($rawMetadata['Creator']);
        } elseif (isset($rawMetadata['Producer'])) {
            $metadataType->setCreator($rawMetadata['Producer']);
        } else {
            $metadataType->setCreator($rawMetadata['Unknown']);
        }
    }

    /**
     * @return Parser
     */
    private function getPdfParser(): Parser
    {
        if (!$this->pdfParser) {
            $this->pdfParser = new Parser();
        }

        return $this->pdfParser;
    }
}
