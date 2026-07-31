<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Document;

use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class DocumentTypeConfig
{
    private const INTEGER_FIELDS = [
        'itemsPerPage',
    ];

    private const BOOLEAN_FIELDS = [
        'displayHeader',
        'displayFooter',
        'displayPageCount',
        'displayLineItems',
        'displayPrices',
    ];

    /**
     * @return array<string, scalar>
     */
    public static function fromXml(\DOMElement $element): array
    {
        $config = [];

        $values = XmlParserUtils::parseChildren($element);

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $config[$key] = match (true) {
                \in_array($key, self::INTEGER_FIELDS, true) => (int) $value,
                \in_array($key, self::BOOLEAN_FIELDS, true) => filter_var($value, \FILTER_VALIDATE_BOOLEAN),
                default => (string) $value,
            };
        }

        return $config;
    }
}
