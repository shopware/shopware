<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage
 */
class DeleteThemeFilesMessageTest extends TestCase
{
    public function testStruct(): void
    {
        $message = new DeleteThemeFilesMessage('path', 'salesChannel', 'theme');

        static::assertEquals('path', $message->getThemePath());
        static::assertEquals('salesChannel', $message->getSalesChannelId());
        static::assertEquals('theme', $message->getThemeId());
    }
}
