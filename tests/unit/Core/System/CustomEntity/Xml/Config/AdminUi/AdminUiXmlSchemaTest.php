<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Exception\CustomEntityXmlParsingException;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\AdminUi;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Card;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\CardField;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Column;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Columns;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Detail;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Entity;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Listing;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Tab;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Tabs;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminUiXmlSchema::class)]
#[CoversClass(AdminUi::class)]
#[CoversClass(Card::class)]
#[CoversClass(CardField::class)]
#[CoversClass(Column::class)]
#[CoversClass(Columns::class)]
#[CoversClass(Detail::class)]
#[CoversClass(Entity::class)]
#[CoversClass(Listing::class)]
#[CoversClass(Tab::class)]
#[CoversClass(Tabs::class)]
class AdminUiXmlSchemaTest extends TestCase
{
    public function testPublicConstants(): void
    {
        static::assertStringEndsWith(
            'System/CustomEntity/Xml/Config/AdminUi/admin-ui-1.0.xsd',
            AdminUiXmlSchema::XSD_FILEPATH
        );
        static::assertEquals('admin-ui.xml', AdminUiXmlSchema::FILENAME);
    }

    public function testCreateFromXmlFileMinSetting(): void
    {
        $entities = $this->getEntities(
            AdminUiXmlSchema::createFromXmlFile(__DIR__ . '/../../../_fixtures/AdminUiXmlSchemaTest/admin-ui.min-setting.xml')
        );

        static::assertCount(1, $entities);

        $this->minSettingsTest(
            $this->checkEntity($entities, 'custom_entity_test')
        );
    }

    public function testCreateFromXmlFileComplex(): void
    {
        $entities = $this->getEntities(
            AdminUiXmlSchema::createFromXmlFile(__DIR__ . '/../../../_fixtures/AdminUiXmlSchemaTest/admin-ui.max-setting.xml')
        );

        static::assertCount(2, $entities);

        $this->minSettingsTest(
            $this->checkEntity($entities, 'custom_entity_simple')
        );

        $customEntityComplex = $this->checkEntity($entities, 'custom_entity_complex');
        $this->checkListing(
            $customEntityComplex,
            [
                'custom_entity_field1',
                'custom_entity_field2',
                'custom_entity_field3',
                'custom_entity_field4',
                'custom_entity_field5',
                'custom_entity_field6',
            ]
        );

        $detail = $customEntityComplex->getDetail();
        $tabs = $detail->getTabs();
        static::assertCount(
            2,
            $tabs->getContent()
        );

        $cards = $this->checkTab($tabs->getContent()[0], 'foo');

        static::assertCount(2, $cards);
        $this->checkCard(
            $cards[0],
            'water',
            [
                'custom_entity_field1',
                'custom_entity_field2',
                'custom_entity_field3',
            ]
        );
        $this->checkCard(
            $cards[1],
            'fire',
            [
                'custom_entity_field4',
                'custom_entity_field5',
            ]
        );

        $cards = $this->checkTab($tabs->getContent()[1], 'bar');
        static::assertCount(3, $cards);

        $this->checkCard(
            $cards[0],
            'stone',
            [
                'custom_entity_field6',
            ]
        );
        $this->checkCard(
            $cards[1],
            'ice',
            [
                'custom_entity_field1',
                'custom_entity_field2',
                'custom_entity_field3',
                'custom_entity_field4',
                'custom_entity_field5',
                'custom_entity_field6',
            ]
        );
        $this->checkCard(
            $cards[2],
            'air',
            [
                'custom_entity_field2',
                'custom_entity_field5',
            ]
        );
    }

    public function testThrowsExceptionWithInvalidPath(): void
    {
        try {
            AdminUiXmlSchema::createFromXmlFile('invalid_path');
            static::fail('no Exception was thrown');
        } catch (CustomEntityXmlParsingException $exception) {
            static::assertEquals(
                'Unable to parse file "invalid_path". Message: Resource "invalid_path" is not a file.',
                $exception->getMessage()
            );
            static::assertEquals('SYSTEM_CUSTOM_ENTITY__XML_PARSE_ERROR', $exception->getErrorCode());
            static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    public function testThrowsExceptionWithXmlFile(): void
    {
        try {
            AdminUiXmlSchema::createFromXmlFile(__DIR__ . '/../../../_fixtures/AdminUiXmlSchemaTest/admin-ui.invalid.xml');
            static::fail('no Exception was thrown');
        } catch (CustomEntityXmlParsingException $exception) {
            // Exception is thrown in listing first
            static::assertStringContainsString(
                'System/CustomEntity/Xml/Config/AdminUi/../../../_fixtures/AdminUiXmlSchemaTest/admin-ui.invalid.xml". Message: [ERROR 1871] Element \'ERROR\': This element is not expected. Expected is ( field ).',
                $exception->getMessage()
            );
            static::assertEquals('SYSTEM_CUSTOM_ENTITY__XML_PARSE_ERROR', $exception->getErrorCode());
            static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    private function minSettingsTest(Entity $customEntityTest): void
    {
        $this->checkListing(
            $customEntityTest,
            ['custom_entity_field']
        );

        $detail = $customEntityTest->getDetail();
        $tabs = $detail->getTabs();
        static::assertCount(1, $tabs->getContent());

        $cards = $this->checkTab($tabs->getContent()[0], 'main');
        static::assertCount(1, $cards);

        $this->checkCard(
            $cards[0],
            'general',
            ['custom_entity_field']
        );
    }

    /**
     * @return Entity[]
     */
    private function getEntities(AdminUiXmlSchema $adminUiXmlSchema): array
    {
        return $adminUiXmlSchema->getAdminUi()->getEntities();
    }

    /**
     * @param Entity[] $entities
     */
    private function checkEntity(array $entities, string $name): Entity
    {
        static::assertInstanceOf(Entity::class, $entities[$name]);
        static::assertEquals($name, $entities[$name]->getName());

        return $entities[$name];
    }

    /**
     * @param list<string> $refs
     */
    private function checkListing(Entity $entity, array $refs): void
    {
        $listing = $entity->getListing();
        $columns = $listing->getColumns();
        static::assertCount(\count($refs), $columns->getContent());

        foreach ($columns->getContent() as $column) {
            static::assertInstanceOf(Column::class, $column);
            static::assertContains($column->getRef(), $refs);
            unset($refs[array_search($column->getRef(), $refs, true)]);
        }
        static::assertCount(0, $refs);
    }

    /**
     * @return Card[]
     */
    private function checkTab(
        Tab $tab,
        string $tabName
    ): array {
        static::assertEquals($tabName, $tab->getName());

        return $tab->getCards();
    }

    /**
     * @param string[] $refs
     */
    private function checkCard(
        Card $card,
        string $tabName,
        array $refs
    ): void {
        static::assertEquals($tabName, $card->getName());

        $fields = $card->getFields();
        static::assertCount(\count($refs), $fields);

        foreach ($fields as $cardField) {
            static::assertInstanceOf(CardField::class, $cardField);
            static::assertContains($cardField->getRef(), $refs);

            unset($refs[array_search($cardField->getRef(), $refs, true)]);
        }
        static::assertCount(0, $refs);
    }
}
