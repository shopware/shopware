<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type\CmsTypes;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;

use function PHPStan\Testing\assertType;

function check(CmsPageEntity $page, string $dynamicType, bool $flag, CustomPage $custom): void
{
    assertType('Shopware\\Core\\Content\\Cms\\Aggregate\\CmsSlot\\CmsSlotEntity<Shopware\\Core\\Content\\Cms\\SalesChannel\\Struct\\ProductListingStruct>|null', $page->getFirstElementOfType('product-listing'));
    assertType('Shopware\\Core\\Content\\Cms\\SalesChannel\\Struct\\ProductListingStruct|null', $page->getFirstElementOfType('product-listing')?->getData());
    assertType('list<Shopware\\Core\\Content\\Cms\\Aggregate\\CmsSlot\\CmsSlotEntity<Shopware\\Core\\Content\\Cms\\SalesChannel\\Struct\\ProductListingStruct>>', $page->getElementsOfType('product-listing'));
    assertType('Shopware\\Core\\Framework\\Struct\\Struct|null', $page->getFirstElementOfType($dynamicType)?->getData());
    assertType('Shopware\\Core\\Framework\\Struct\\Struct|null', $page->getFirstElementOfType('plugin-unknown')?->getData());
    assertType('Shopware\\Core\\Content\\Cms\\SalesChannel\\Struct\\ProductListingStruct|Shopware\\Core\\Content\\Cms\\SalesChannel\\Struct\\TextStruct|null', $page->getFirstElementOfType($flag ? 'text' : 'product-listing')?->getData());
    assertType('Shopware\\Core\\Framework\\Struct\\Struct|null', $page->getFirstElementOfType($flag ? 'text' : 'plugin-unknown')?->getData());
    foreach ($page->getElementsOfType('text') as $slot) {
        assertType('Shopware\\Core\\Content\\Cms\\SalesChannel\\Struct\\TextStruct|null', $slot->getData());
    }
    $plainSlot = new CmsSlotEntity();
    $plainSlot->setData(new TextStruct());
    assertType('Shopware\\Core\\Framework\\Struct\\Struct|null', $plainSlot->getData());
    assertType('Shopware\\Core\\Framework\\Struct\\Struct|null', $custom->getFirstElementOfType('product-listing')?->getData());
}
