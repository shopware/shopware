<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\AdminUi;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Entity as AdminUiEntity;

/**
 * @internal
 */
#[CoversClass(AdminUi::class)]
class AdminUiTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $adminUiElement = $dom->createElement('adminUi');
        $adminUiEntityElement = $dom->createElement('entity');

        $adminUiEntityElement->setAttribute('name', 'AdminUiTest');
        $adminUiEntityElement->setAttribute('icon', 'triangle');
        $adminUiEntityElement->setAttribute('color', 'red');
        $adminUiEntityElement->setAttribute('position', '1');
        $adminUiEntityElement->setAttribute('navigation-parent', 'test');

        $adminUiElement->appendChild(
            $adminUiEntityElement
        );

        $adminUi = AdminUi::fromXml($adminUiElement);

        $adminUiEntities = $adminUi->getEntities();
        static::assertInstanceOf(AdminUiEntity::class, \array_pop($adminUiEntities));
    }
}
