<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductExport\Validator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Validator\XmlValidator;

class XmlValidatorTest extends TestCase
{
    public function testEntityLoaderIsFalseAsBefore(): void
    {
        if (\PHP_VERSION_ID >= 80000) {
            static::markTestSkipped();
        }

        $previous = libxml_disable_entity_loader(false);

        $xmlValidator = new XmlValidator();
        $productExportEntity = new ProductExportEntity();
        $productExportEntity->setFileFormat($productExportEntity::FILE_FORMAT_XML);
        $errors = new ErrorCollection();

        $xmlValidator->validate($productExportEntity, $this->createTestXmlFixture(), $errors);

        static::assertFalse(libxml_disable_entity_loader($previous));
    }

    public function testEntityLoaderIsTrueAsBefore(): void
    {
        if (\PHP_VERSION_ID >= 80000) {
            static::markTestSkipped();
        }

        $previous = libxml_disable_entity_loader(true);

        $xmlValidator = new XmlValidator();
        $productExportEntity = new ProductExportEntity();
        $productExportEntity->setFileFormat($productExportEntity::FILE_FORMAT_XML);
        $errors = new ErrorCollection();

        $xmlValidator->validate($productExportEntity, $this->createTestXmlFixture(), $errors);

        static::assertTrue(libxml_disable_entity_loader($previous));
    }

    private function createTestXmlFixture()
    {
        $xml = <<<EOD
<?xml version="1.0"?>
<!DOCTYPE root>
<test><testing>Test</testing></test>
EOD;

        return $xml;
    }
}
