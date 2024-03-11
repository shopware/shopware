<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Column;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Columns;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(Columns::class)]
class ColumnsTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $columnsElement = $dom->createElement('columns');
        $columnElement = $dom->createElement('column');

        $columnsElement->appendChild(
            $columnElement
        );

        $columns = Columns::fromXml($columnsElement);
        static::assertInstanceOf(Columns::class, $columns);

        $columnsList = $columns->getContent();
        static::assertIsArray($columnsList);
        static::assertInstanceOf(Column::class, \array_pop($columnsList));
    }

    public function testJsonSerialize(): void
    {
        $dom = new \DOMDocument();
        $columnsElement = $dom->createElement('columns');
        $columnElement0 = $dom->createElement('column');
        $columnElement1 = $dom->createElement('column');

        $columnsElement->appendChild(
            $columnElement0
        );
        $columnsElement->appendChild(
            $columnElement1
        );

        $columns = Columns::fromXml($columnsElement);

        $serializedColumns = $columns->jsonSerialize();

        static::assertEquals(
            [
                0 => Column::fromXml($columnElement0),
                1 => Column::fromXml($columnElement1),
            ],
            $serializedColumns
        );
    }
}
