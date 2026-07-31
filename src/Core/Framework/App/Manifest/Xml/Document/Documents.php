<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Document;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Documents extends XmlElement
{
    /**
     * @var list<DocumentType>
     */
    protected array $documentTypes = [];

    /**
     * @return list<DocumentType>
     */
    public function getDocumentTypes(): array
    {
        return $this->documentTypes;
    }

    protected static function parse(\DOMElement $element): array
    {
        $documentTypes = [];

        foreach ($element->getElementsByTagName('document-type') as $documentType) {
            $documentTypes[] = DocumentType::fromXml($documentType);
        }

        return ['documentTypes' => $documentTypes];
    }
}
