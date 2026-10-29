<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductDocumentAllowedExtensionsEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentAllowedExtensionsEvent::class)]
class ProductDocumentAllowedExtensionsEventTest extends TestCase
{
    public function testAListenerReplacesTheFileTypes(): void
    {
        $context = Context::createDefaultContext();
        $event = new ProductDocumentAllowedExtensionsEvent(['pdf', 'docx'], $context);

        $event->setAllowedExtensions(['pdf']);

        static::assertSame(['pdf'], $event->getAllowedExtensions());
        static::assertSame($context, $event->getContext());
    }
}
