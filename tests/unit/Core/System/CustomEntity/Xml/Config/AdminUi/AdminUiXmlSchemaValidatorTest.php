<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Entity as AdminUiEntity;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Entity;

/**
 * @internal
 */
#[CoversClass(AdminUiXmlSchemaValidator::class)]
class AdminUiXmlSchemaValidatorTest extends TestCase
{
    public function testThatNoExceptionIsThrown(): void
    {
        // no Exception will be thrown
        $this->validate('noExceptions');
    }

    public function testThatInvalidReferencesIsThrownCausedInColumns(): void
    {
        $this->expectExceptionObject(CustomEntityException::invalidReferences('admin-ui.xml', 'ce_invalid_ref_in_columns', '<listing>', ['i_am_an_invalid_reference']));
        $this->validate('invalidReferences/inColumns');
    }

    public function testThatInvalidReferencesIsThrownCausedInCard(): void
    {
        $this->expectExceptionObject(CustomEntityException::invalidReferences('admin-ui.xml', 'ce_invalid_ref_in_card', '<detail>', ['i_am_an_invalid_reference']));
        $this->validate('invalidReferences/inCard');
    }

    public function testThatInvalidReferencesIsThrownComplex(): void
    {
        $this->expectExceptionObject(CustomEntityException::invalidReferences('admin-ui.xml', 'ce_invalid_ref_complex', '<listing>', ['i_am_an_invalid_reference']));
        $this->validate('invalidReferences/complex');
    }

    public function testThatDuplicateReferencesIsThrownCausedInColumns(): void
    {
        $this->expectExceptionObject(CustomEntityException::duplicateReferences('admin-ui.xml', 'ce_duplicate_ref_in_columns', '<listing>', ['test_string']));
        $this->validate('duplicateReferences/inColumns');
    }

    public function testThatDuplicateReferencesIsThrownCausedInCard(): void
    {
        $this->expectExceptionObject(CustomEntityException::duplicateReferences('admin-ui.xml', 'ce_duplicate_ref_in_card', '<detail>', ['test_string']));
        $this->validate('duplicateReferences/inCard');
    }

    public function testThatDuplicateReferencesIsThrownComplex(): void
    {
        $this->expectExceptionObject(CustomEntityException::duplicateReferences('admin-ui.xml', 'ce_duplicate_ref_complex', '<listing>', ['test_float']));
        $this->validate('duplicateReferences/complex');
    }

    private function validate(string $fixturePath): void
    {
        $adminUiXmlSchema = AdminUiXmlSchema::createFromXmlFile(
            __DIR__ . "/../../../_fixtures/AdminUiXmlSchemaValidatorTest/$fixturePath/" . AdminUiXmlSchema::FILENAME
        );
        $customEntityXmlSchema = CustomEntityXmlSchema::createFromXmlFile(
            __DIR__ . "/../../../_fixtures/AdminUiXmlSchemaValidatorTest/$fixturePath/" . CustomEntityXmlSchema::FILENAME
        );

        $adminUiEntities = $adminUiXmlSchema->getAdminUi()->getEntities();
        static::assertNotNull($customEntities = $customEntityXmlSchema->getEntities()?->getEntities());
        static::assertInstanceOf(AdminUiEntity::class, $adminUiEntity = \array_pop($adminUiEntities));
        static::assertInstanceOf(Entity::class, $customEntity = \array_pop($customEntities));
        (new AdminUiXmlSchemaValidator())->validateConfigurations(
            $adminUiEntity,
            $customEntity,
        );
    }
}
