<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesMessage::class)]
class DeleteThemeFilesMessageTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testStruct(): void
    {
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getSalesChannelId()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getThemeId()" is deprecated and will be removed in v6.8.0.0.');
        $this->expectUserDeprecationMessage('Method "Shopware\\Storefront\\Theme\\Message\\DeleteThemeFilesMessage::getThemePath()" is deprecated and will be removed in v6.8.0.0.');

        $message = new DeleteThemeFilesMessage('path', 'salesChannel', 'theme');

        static::assertSame('path', $message->getThemePath());
        static::assertSame('salesChannel', $message->getSalesChannelId());
        static::assertSame('theme', $message->getThemeId());
    }
}
