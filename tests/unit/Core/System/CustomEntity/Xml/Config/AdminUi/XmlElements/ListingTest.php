<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Listing;

/**
 * @internal
 */
#[CoversClass(Listing::class)]
class ListingTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $listingElement = $dom->createElement('listing');
        $columnsElement = $dom->createElement('columns');

        $listingElement->appendChild(
            $columnsElement
        );

        $listing = Listing::fromXml($listingElement);
        $columns = $listing->getColumns();
        static::assertSame([], $columns->getContent());
    }
}
