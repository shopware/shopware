<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\Type\DocumentMetadata;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;
use Smalot\PdfParser\Parser;

class PdfParserLoader implements MetadataLoaderInterface
{
    const MAX_FILE_SIZE = 128000000; // 128mb

    /**
     * @var Parser|null
     */
    private $pdfParser;

    /**
     * @param string $filePath
     *
     * @return array
     */
    public function extractMetadata(string $filePath): array
    {
        if (!$this->isAllowedToHandle($filePath)) {
            throw new CanNotLoadMetadataException(sprintf('File %s is not supported by library pdfparser', $filePath));
        }

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

    private function isAllowedToHandle(string $filePath)
    {
        $isPdf = mime_content_type($filePath) === 'application/pdf';
        $isTooLarge = filesize($filePath) > self::MAX_FILE_SIZE;

        return $isPdf && !$isTooLarge;
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
