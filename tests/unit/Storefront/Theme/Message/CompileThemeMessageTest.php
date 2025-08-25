<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;

/**
 * @internal
 */
#[CoversClass(CompileThemeMessage::class)]
class CompileThemeMessageTest extends TestCase
{
    public function testStruct(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::SALES_CHANNEL, $themeId, true, $context);

        static::assertSame($themeId, $message->getThemeId());
        static::assertSame(TestDefaults::SALES_CHANNEL, $message->getSalesChannelId());
        static::assertTrue($message->isWithAssets());
        static::assertSame($context, $message->getContext());
    }
}
