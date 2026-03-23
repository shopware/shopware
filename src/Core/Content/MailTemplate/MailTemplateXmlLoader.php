<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Content\MailTemplate\Xml\MailTemplates;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

#[Package('after-sales')]
class MailTemplateXmlLoader
{
    private const XSD_FILE = __DIR__ . '/Schema/mail-templates-1.0.xsd';

    public static function load(string $xmlFile): MailTemplates
    {
        $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);

        $mailTemplates = $doc->getElementsByTagName('mail-templates')->item(0);
        \assert($mailTemplates instanceof \DOMElement);

        return MailTemplates::fromXml($mailTemplates);
    }
}
