<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigTimezoneOverride;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(TwigTimezoneOverride::class)]
class TwigTimezoneOverrideTest extends TestCase
{
    private Environment $twig;

    private CoreExtension $coreExtension;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader());
        $this->coreExtension = $this->twig->getExtension(CoreExtension::class);
        $this->coreExtension->setTimezone('UTC');
    }

    public function testNullTimezonePassesThroughWithoutMutation(): void
    {
        $result = TwigTimezoneOverride::run($this->twig, null, static fn () => 'rendered');

        static::assertSame('rendered', $result);
        static::assertSame('UTC', $this->coreExtension->getTimezone()->getName());
    }

    public function testAppliesStringTimezoneAndRestoresAfterwards(): void
    {
        $observed = null;
        $result = TwigTimezoneOverride::run($this->twig, 'Europe/Berlin', function () use (&$observed) {
            $observed = $this->coreExtension->getTimezone()->getName();

            return 42;
        });

        static::assertSame(42, $result);
        static::assertSame('Europe/Berlin', $observed);
        static::assertSame('UTC', $this->coreExtension->getTimezone()->getName());
    }

    public function testAppliesDateTimeZoneObjectAndRestoresAfterwards(): void
    {
        $observed = null;
        $result = TwigTimezoneOverride::run($this->twig, new \DateTimeZone('Europe/Berlin'), function () use (&$observed) {
            $observed = $this->coreExtension->getTimezone()->getName();

            return 'ok';
        });

        static::assertSame('ok', $result);
        static::assertSame('Europe/Berlin', $observed);
        static::assertSame('UTC', $this->coreExtension->getTimezone()->getName());
    }

    public function testEmptyStringPassesThroughWithoutMutation(): void
    {
        $result = TwigTimezoneOverride::run($this->twig, '', static fn () => 'rendered');

        static::assertSame('rendered', $result);
        static::assertSame('UTC', $this->coreExtension->getTimezone()->getName());
    }

    public function testRestoresTimezoneWhenCallbackThrows(): void
    {
        static::expectExceptionObject(new \RuntimeException('boom'));

        try {
            TwigTimezoneOverride::run($this->twig, 'Europe/Berlin', static function (): never {
                throw new \RuntimeException('boom');
            });
        } finally {
            static::assertSame('UTC', $this->coreExtension->getTimezone()->getName());
        }
    }
}
