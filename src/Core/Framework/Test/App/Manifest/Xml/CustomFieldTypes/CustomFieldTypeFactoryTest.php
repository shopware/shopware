<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\CustomFieldTypeNotFoundException;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldTypeFactory;

class CustomFieldTypeFactoryTest extends TestCase
{
    public function testCreateFromXmlThrowsExceptionOnInvalidTag(): void
    {
        self::expectException(CustomFieldTypeNotFoundException::class);
        CustomFieldTypeFactory::createFromXml(new \DOMElement('invalid'));
    }
}
