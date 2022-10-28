<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi;

use PHPUnit\Framework\TestCase;
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

/**
 * @internal
 * @covers \Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema
 */
class AdminUiXmlSchemaTest extends TestCase
{
    private const TEST_LOCALE = 'en-GB';

    public function testPublicConstants(): void
    {
        static::assertStringEndsWith(
            'src/Core/System/CustomEntity/Xml/Config/AdminUi/admin-ui-1.0.xsd',
            AdminUiXmlSchema::XSD_FILEPATH
        );
        static::assertEquals('admin-ui.xml', AdminUiXmlSchema::FILENAME);
    }

    public function testConstructor(): void
    {
        $adminUi = new AdminUi();
        $adminUiXmlSchema = new AdminUiXmlSchema($adminUi);
        static::assertEquals($adminUi, $adminUiXmlSchema->getAdminUi());
    }

    public function testCreateFromXmlFileMinSetting(): void
    {
        $entities = $this->getEntities(
            AdminUiXmlSchema::createFromXmlFile(__DIR__ . '/../../../_fixtures/admin-ui.min-setting.xml')
        );

        static::assertIsArray($entities);
        static::assertCount(1, $entities);

        $this->minSettingsTest(
            $this->checkEntity($entities, 'custom_entity_test')
        );
    }

    public function testCreateFromXmlFileComplex(): void
    {
        $entities = $this->getEntities(
            AdminUiXmlSchema::createFromXmlFile(__DIR__ . '/../../../_fixtures/admin-ui.max-setting.xml')
        );

        static::assertIsArray($entities);
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
        static::assertInstanceOf(Detail::class, $detail);

        $tabs = $detail->getTabs();
        static::assertInstanceOf(Tabs::class, $tabs);
        static::assertCount(
            2,
            $tabs->toArray(self::TEST_LOCALE)
        );

        $cards = $this->checkTab($tabs->{0}, 'foo');
        static::assertIsArray($cards);
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

        $cards = $this->checkTab($tabs->{1}, 'bar');
        static::assertIsArray($cards);
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

    private function minSettingsTest(Entity $customEntityTest): void
    {
        $this->checkListing(
            $customEntityTest,
            ['custom_entity_field']
        );

        $detail = $customEntityTest->getDetail();
        static::assertInstanceOf(Detail::class, $detail);

        $tabs = $detail->getTabs();
        static::assertInstanceOf(Tabs::class, $tabs);
        static::assertCount(1, $tabs->toArray(self::TEST_LOCALE));

        $cards = $this->checkTab($tabs->{0}, 'main');
        static::assertIsArray($cards);
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
        static::assertInstanceOf(AdminUiXmlSchema::class, $adminUiXmlSchema);

        $adminUi = $adminUiXmlSchema->getAdminUi();
        static::assertInstanceOf(AdminUi::class, $adminUi);

        return $adminUi->getEntities();
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
     * @param string[] $refs
     */
    private function checkListing(Entity $entity, array $refs): void
    {
        $listing = $entity->getListing();
        static::assertInstanceOf(Listing::class, $listing);

        $columns = $listing->getColumns();
        static::assertInstanceOf(Columns::class, $columns);
        static::assertCount(\count($refs), $columns->toArray(self::TEST_LOCALE));

        foreach ($columns->toArray(self::TEST_LOCALE) as $column) {
            static::assertInstanceOf(Column::class, $column);
            static::assertIsString($column->getRef());
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
        static::assertInstanceOf(Tab::class, $tab);
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
            static::assertIsString($cardField->getRef());
            static::assertContains($cardField->getRef(), $refs);

            unset($refs[array_search($cardField->getRef(), $refs, true)]);
        }
        static::assertCount(0, $refs);
    }
}
