<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Entity as AdminUiEntity;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityConfigurationException;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Entity;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminUiXmlSchemaValidator::class)]
#[CoversClass(CustomEntityConfigurationException::class)]
class AdminUiXmlSchemaValidatorTest extends TestCase
{
    public function testThatNoExceptionIsThrown(): void
    {
        // no Exception will be thrown
        $this->validate('noExceptions');
    }

    public function testThatInvalidReferencesIsThrownCausedInColumns(): void
    {
        try {
            $this->validate('invalidReferences/inColumns');
            static::fail('no Exception was thrown');
        } catch (CustomEntityConfigurationException $exception) {
            static::assertEquals(
                'In `admin-ui.xml` the entity `ce_invalid_ref_in_columns` has invalid references (regarding `entities.xml`) inside of `<listing>`: i_am_an_invalid_reference',
                $exception->getMessage()
            );
            static::assertEquals(CustomEntityConfigurationException::INVALID_REFERENCES, $exception->getErrorCode());
            static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        }
    }

    public function testThatInvalidReferencesIsThrownCausedInCard(): void
    {
        try {
            $this->validate('invalidReferences/inCard');
            static::fail('no Exception was thrown');
        } catch (CustomEntityConfigurationException $exception) {
            static::assertEquals(
                'In `admin-ui.xml` the entity `ce_invalid_ref_in_card` has invalid references (regarding `entities.xml`) inside of `<detail>`: i_am_an_invalid_reference',
                $exception->getMessage()
            );
            static::assertEquals(CustomEntityConfigurationException::INVALID_REFERENCES, $exception->getErrorCode());
            static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        }
    }

    public function testThatInvalidReferencesIsThrownComplex(): void
    {
        try {
            $this->validate('invalidReferences/complex');
            static::fail('no Exception was thrown');
        } catch (CustomEntityConfigurationException $exception) {
            // Exception is thrown in listing first
            static::assertEquals(
                'In `admin-ui.xml` the entity `ce_invalid_ref_complex` has invalid references (regarding `entities.xml`) inside of `<listing>`: i_am_an_invalid_reference',
                $exception->getMessage()
            );
            static::assertEquals(CustomEntityConfigurationException::INVALID_REFERENCES, $exception->getErrorCode());
            static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        }
    }

    public function testThatDuplicateReferencesIsThrownCausedInColumns(): void
    {
        try {
            $this->validate('duplicateReferences/inColumns');
            static::fail('no Exception was thrown');
        } catch (CustomEntityConfigurationException $exception) {
            static::assertEquals(
                'In `admin-ui.xml`, the entity `ce_duplicate_ref_in_columns` only allows unique fields per xml element, but found the following duplicates inside of `<listing>`: test_string',
                $exception->getMessage()
            );
            static::assertEquals(CustomEntityConfigurationException::DUPLICATE_REFERENCES, $exception->getErrorCode());
            static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        }
    }

    public function testThatDuplicateReferencesIsThrownCausedInCard(): void
    {
        try {
            $this->validate('duplicateReferences/inCard');
            static::fail('no Exception was thrown');
        } catch (CustomEntityConfigurationException $exception) {
            static::assertEquals(
                'In `admin-ui.xml`, the entity `ce_duplicate_ref_in_card` only allows unique fields per xml element, but found the following duplicates inside of `<detail>`: test_string',
                $exception->getMessage()
            );
            static::assertEquals(CustomEntityConfigurationException::DUPLICATE_REFERENCES, $exception->getErrorCode());
            static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        }
    }

    public function testThatDuplicateReferencesIsThrownComplex(): void
    {
        try {
            $this->validate('duplicateReferences/complex');
            static::fail('no Exception was thrown');
        } catch (CustomEntityConfigurationException $exception) {
            // Exception is thrown in listing first
            static::assertEquals(
                'In `admin-ui.xml`, the entity `ce_duplicate_ref_complex` only allows unique fields per xml element, but found the following duplicates inside of `<listing>`: test_float',
                $exception->getMessage()
            );
            static::assertEquals(CustomEntityConfigurationException::DUPLICATE_REFERENCES, $exception->getErrorCode());
            static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        }
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
