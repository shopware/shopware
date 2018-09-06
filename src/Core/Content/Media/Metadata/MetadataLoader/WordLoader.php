<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\Type\DocumentMetadata;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;

class WordLoader implements MetadataLoaderInterface
{
    private const SUPPORTED_READER_NAMES = [
        'Word2007',
        'MsDoc',
    ];

    private const SUPPORTED_FILE_EXTENSIONS = [
        'doc',
        'docx',
    ];

    public function extractMetadata(string $filePath): array
    {
        $document = $this->loadDocument($filePath);

        $docInfo = $document->getDocInfo();

        return [
            'title' => $docInfo->getTitle(),
            'description' => $docInfo->getDescription(),

            'creator' => $docInfo->getCreator(),

            'created' => $docInfo->getCreated(),
            'lastModified' => $docInfo->getLastModifiedBy(),

            'company' => $docInfo->getCompany(),
            'category' => $docInfo->getCategory(),
            'keywords' => $docInfo->getKeywords(),
        ];
    }

    public function enhanceTypeObject(MetadataType $metadataType, array $rawMetadata): void
    {
        if (!$metadataType instanceof DocumentMetadata) {
            return;
        }

        $metadataType->setTitle($rawMetadata['title']);
        $metadataType->setCreator($rawMetadata['creator']);
    }

    /**
     * @throws CanNotLoadMetadataException
     */
    private function loadDocument(string $filePath): PhpWord
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        if (!in_array($fileExtension, self::SUPPORTED_FILE_EXTENSIONS, true)) {
            throw new CanNotLoadMetadataException('File extension not supported');
        }

        foreach (self::SUPPORTED_READER_NAMES as $readerName) {
            $reader = IOFactory::createReader($readerName);

            try {
                return @$reader->load($filePath);
            } catch (\Throwable $e) {
                continue;
            }
        }

        throw new CanNotLoadMetadataException('File not supported');
    }
}
