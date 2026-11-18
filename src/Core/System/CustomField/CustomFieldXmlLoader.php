<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlParser;
use Shopware\Core\System\CustomField\Xml\CustomFields;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldXmlLoader
{
    private const XSD_FILE = __DIR__ . '/Schema/custom-fields-1.0.xsd';

    public static function load(string $xmlFile): CustomFields
    {
        $doc = XmlParser::loadFile($xmlFile, self::XSD_FILE);

        $customFields = $doc->getElementsByTagName('custom-fields')->item(0);
        \assert($customFields instanceof \DOMElement);

        return CustomFields::fromXml($customFields);
    }
}
