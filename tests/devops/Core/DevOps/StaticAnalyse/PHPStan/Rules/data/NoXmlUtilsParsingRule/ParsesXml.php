<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoXmlUtilsParsingRule;

use Shopware\Core\Framework\Util\XmlParser;
use Symfony\Component\Config\Util\XmlUtils;

class ParsesXml
{
    public function parse(string $xml, string $file): array
    {
        $first = XmlUtils::parse($xml);
        $second = XmlUtils::loadFile($file);
        $third = XmlParser::parse($xml);
        $fourth = XmlParser::loadFile($file);
        $value = XmlUtils::phpize('true');

        return [$first, $second, $third, $fourth, $value];
    }
}
