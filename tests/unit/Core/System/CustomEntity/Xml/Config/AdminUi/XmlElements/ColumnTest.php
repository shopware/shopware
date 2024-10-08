<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Column;

/**
 * @internal
 */
#[CoversClass(Column::class)]
class ColumnTest extends TestCase
{
    #[DataProvider('provider')]
    public function testFromXml(?string $hidden, bool $result): void
    {
        $dom = new \DOMDocument();
        $columnElement = $dom->createElement('column');

        $columnElement->setAttribute('ref', 'column ref');
        if ($hidden !== null) {
            $columnElement->setAttribute('hidden', $hidden);
        }

        $column = Column::fromXml($columnElement);
        static::assertEquals($result, $column->isHidden());
        static::assertEquals('column ref', $column->getRef());
    }

    public static function provider(): \Generator
    {
        yield 'is hidden' => ['hidden' => 'true', 'result' => true];
        yield 'is visible' => ['hidden' => 'false', 'result' => false];
        yield 'is undefined' => ['hidden' => null, 'result' => false];
    }
}
