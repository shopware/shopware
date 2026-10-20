<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Document;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * Parses the manifest `<documents>` block into raw document-type declarations. The typed
 * feature value object is built later by the document feature definition.
 *
 * @internal only for use by the app-system
 *
 * @phpstan-type DocumentTypeArray array{identifier: string, label: array<string, string>, formats: list<string>, config: array<string, scalar>}
 */
#[Package('framework')]
class Documents extends XmlElement
{
    private const INTEGER_CONFIG_FIELDS = [
        'itemsPerPage',
    ];

    private const BOOLEAN_CONFIG_FIELDS = [
        'displayHeader',
        'displayFooter',
        'displayPageCount',
        'displayLineItems',
        'displayPrices',
        'displayCompanyAddress',
        'displayReturnAddress',
        'displayCustomerVatId',
        'displayLineItemPosition',
        'displayDivergentDeliveryAddress',
    ];

    /**
     * @var list<DocumentTypeArray>
     */
    protected array $documentTypes = [];

    /**
     * @return list<DocumentTypeArray>
     */
    public function getDocumentTypes(): array
    {
        return $this->documentTypes;
    }

    protected static function parse(\DOMElement $element): array
    {
        $documentTypes = [];

        foreach ($element->getElementsByTagName('document-type') as $documentType) {
            $documentTypes[] = self::parseDocumentType($documentType);
        }

        return ['documentTypes' => $documentTypes];
    }

    /**
     * @return DocumentTypeArray
     */
    private static function parseDocumentType(\DOMElement $element): array
    {
        $values = XmlParserUtils::parseChildrenAndTranslate($element, ['label']);

        $formats = [];
        $formatsElement = $element->getElementsByTagName('formats')->item(0);

        if ($formatsElement instanceof \DOMElement) {
            foreach ($formatsElement->getElementsByTagName('format') as $format) {
                $formats[] = (string) $format->nodeValue;
            }
        }

        $config = [];
        $configElement = $element->getElementsByTagName('config')->item(0);

        if ($configElement instanceof \DOMElement) {
            $config = self::parseConfig($configElement);
        }

        /** @var array<string, string> $label */
        $label = $values['label'] ?? [];

        $identifier = $values['identifier'] ?? '';
        $identifier = \is_string($identifier) ? $identifier : '';

        foreach (['identifier' => $identifier, 'label' => $label, 'formats' => $formats] as $field => $value) {
            if ($value === '' || $value === []) {
                throw AppException::invalidArgument($field . ' must not be empty');
            }
        }

        return [
            'identifier' => $identifier,
            'label' => $label,
            'formats' => $formats,
            'config' => $config,
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private static function parseConfig(\DOMElement $element): array
    {
        $config = [];

        foreach (XmlParserUtils::parseChildren($element) as $key => $value) {
            if ($value === null) {
                continue;
            }

            $config[$key] = match (true) {
                \in_array($key, self::INTEGER_CONFIG_FIELDS, true) => (int) $value,
                \in_array($key, self::BOOLEAN_CONFIG_FIELDS, true) => filter_var($value, \FILTER_VALIDATE_BOOLEAN),
                default => (string) $value,
            };
        }

        return $config;
    }
}
