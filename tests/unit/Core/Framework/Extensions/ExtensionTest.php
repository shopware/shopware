<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Extensions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;

/**
 * @internal
 */
#[CoversClass(Extension::class)]
class ExtensionTest extends TestCase
{
    public function testOnPreReturnsPreEventName(): void
    {
        $extension = new class extends Extension {
            public const NAME = 'test.extension';
        };

        static::assertSame('test.extension.pre', $extension::onPre());
        // Helper must stay in lockstep with ExtensionDispatcher::pre() so subscribers
        // using either style resolve to the same event name.
        static::assertSame(ExtensionDispatcher::pre($extension::NAME), $extension::onPre());
    }

    public function testOnPostReturnsPostEventName(): void
    {
        $extension = new class extends Extension {
            public const NAME = 'test.extension';
        };

        static::assertSame('test.extension.post', $extension::onPost());
        static::assertSame(ExtensionDispatcher::post($extension::NAME), $extension::onPost());
    }

    public function testOnErrorReturnsErrorEventName(): void
    {
        $extension = new class extends Extension {
            public const NAME = 'test.extension';
        };

        static::assertSame('test.extension.error', $extension::onError());
        static::assertSame(ExtensionDispatcher::error($extension::NAME), $extension::onError());
    }

    public function testHelpersUseLateStaticBindingAcrossSubclasses(): void
    {
        $a = new class extends Extension {
            public const NAME = 'a.extension';
        };
        $b = new class extends Extension {
            public const NAME = 'b.extension';
        };

        static::assertSame('a.extension.pre', $a::onPre());
        static::assertSame('b.extension.pre', $b::onPre());
        static::assertSame('a.extension.post', $a::onPost());
        static::assertSame('b.extension.post', $b::onPost());
        static::assertSame('a.extension.error', $a::onError());
        static::assertSame('b.extension.error', $b::onError());
    }
}
