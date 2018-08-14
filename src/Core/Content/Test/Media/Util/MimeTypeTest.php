<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Util;

use Shopware\Core\Content\Media\Exception\IllegalMimeTypeException;
use Shopware\Core\Content\Media\Util\MimeType;

class MimeTypeTest extends \PHPUnit\Framework\TestCase
{
    public function testIsSupported()
    {
        static::assertTrue(MimeType::isSupported('image/png'));
        static::assertFalse(MimeType::isSupported('not/existant'));
        static::assertFalse(MimeType::isSupported(''));
    }

    public function testThrowsExceptionForUnknownMimeType()
    {
        static::expectException(IllegalMimeTypeException::class);
        MimeType::getExtension('no/mimeType');
    }

    public function testGetExtensionForPng()
    {
        static::assertEquals('.png', MimeType::getExtension('image/png'));
    }
}
