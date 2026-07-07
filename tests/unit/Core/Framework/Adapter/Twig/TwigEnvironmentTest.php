<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Feature;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(TwigEnvironment::class)]
class TwigEnvironmentTest extends TestCase
{
    public function testUsesShopwareGetAttributeFunctionAndCachedEscaperRuntime(): void
    {
        $code = (new TwigEnvironment(new ArrayLoader(['bla' => '{{ test.bla }}'])))
            ->compileSource(new Source('{{ test.bla }}', 'bla'));

        static::assertStringContainsString('\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute', $code);
        static::assertStringContainsString('\Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime::escape($this->env->getRuntime(\'Twig\\Runtime\\EscaperRuntime\'),', $code);
    }

    public function testMarkupEscapeIsWorkingCorrectly(): void
    {
        $template = <<<'TWIG'
{% for name in names %}
    {% set captured %}{{ name }}{% endset %}
    Hello {{ captured|trim|e }}
{% endfor %}
TWIG;

        $names = [
            'John Doe',
            'Jane Doe',
            'Peter Doe',
            'Hans Doe',
            'Harald Doe',
            'Will Doe',
        ];
        $renderedTemplate = (new TwigEnvironment(new ArrayLoader(['test' => $template])))
            ->render('test', ['names' => $names]);

        foreach ($names as $name) {
            static::assertStringContainsString('Hello ' . $name, $renderedTemplate);
        }
    }

    public function testRenderWithTimezoneOverridePassesThroughNullTimezoneWithoutMutation(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-01', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ]));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverridePassesThroughEmptyTimezoneWithoutMutation(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-01', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ], ''));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverrideAppliesStringTimezoneAndRestoresAfterwards(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ], 'Europe/Berlin'));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverrideAppliesDateTimeZoneObjectAndRestoresAfterwards(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ], new \DateTimeZone('Europe/Berlin')));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverrideRestoresTimezoneWhenRenderThrows(): void
    {
        $exception = new \RuntimeException('boom');
        $twig = $this->getMockBuilder(TwigEnvironment::class)
            ->setConstructorArgs([new ArrayLoader(['test' => ''])])
            ->onlyMethods(['render'])
            ->getMock();
        $twig->method('render')->willThrowException($exception);
        $this->getCoreExtension($twig)->setTimezone('UTC');

        static::expectExceptionObject($exception);

        try {
            $twig->renderWithTimezoneOverride('test', [], 'Europe/Berlin');
        } finally {
            static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
        }
    }

    public function testRenderWithTimezoneOverrideFallsBackToConfiguredTimezoneInV680(): void
    {
        $twig = $this->createTimezoneTestTwig();
        $this->getCoreExtension($twig)->setTimezone('Europe/Berlin');
        $twig->overrideTimezone('America/New_York');

        static::assertSame('America/New_York', $this->getCoreExtension($twig)->getTimezone()->getName());

        Feature::fake(['v6.8.0.0'], function () use ($twig): void {
            static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
                'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
            ]));
            static::assertSame('America/New_York', $this->getCoreExtension($twig)->getTimezone()->getName());
        });
    }

    public function testRenderWithTimezoneOverridePrefersExplicitTimezoneOverConfiguredInV680(): void
    {
        $twig = $this->createTimezoneTestTwig();
        $twig->overrideTimezone('America/New_York');

        Feature::fake(['v6.8.0.0'], function () use ($twig): void {
            static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
                'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
            ], 'Europe/Berlin'));
        });
    }

    public function testRenderWithTimezoneOverrideWithoutPriorOverrideRendersUnchangedInV680(): void
    {
        $twig = $this->createTimezoneTestTwig();

        Feature::fake(['v6.8.0.0'], function () use ($twig): void {
            static::assertSame('2026-01-01', $twig->renderWithTimezoneOverride('test', [
                'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
            ]));
            static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
        });
    }

    public function testRenderWithTimezoneOverrideIgnoresConfiguredTimezoneBeforeV680(): void
    {
        $twig = $this->createTimezoneTestTwig();
        $this->getCoreExtension($twig)->setTimezone('Europe/Berlin');
        $twig->overrideTimezone('UTC');

        Feature::fake([], function () use ($twig): void {
            static::assertSame('2026-01-01', $twig->renderWithTimezoneOverride('test', [
                'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
            ]));
        });
    }

    public function testOverrideTimezoneKeepsFirstConfiguredValue(): void
    {
        $twig = $this->createTimezoneTestTwig();
        $this->getCoreExtension($twig)->setTimezone('Europe/Berlin');
        $twig->overrideTimezone('America/New_York');
        $twig->overrideTimezone('UTC');

        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());

        Feature::fake(['v6.8.0.0'], function () use ($twig): void {
            static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
                'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
            ]));
        });
    }

    public function testOverrideTimezoneWithoutCoreExtensionDoesNothing(): void
    {
        $twig = $this->getMockBuilder(TwigEnvironment::class)
            ->setConstructorArgs([new ArrayLoader()])
            ->onlyMethods(['hasExtension'])
            ->getMock();
        $twig->method('hasExtension')->willReturn(false);
        $this->getCoreExtension($twig)->setTimezone('UTC');

        $twig->overrideTimezone('Europe/Berlin');

        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    private function createTimezoneTestTwig(): TwigEnvironment
    {
        $twig = new TwigEnvironment(new ArrayLoader([
            'test' => '{{ testDate|date("Y-m-d") }}',
        ]));
        $this->getCoreExtension($twig)->setTimezone('UTC');

        return $twig;
    }

    private function getCoreExtension(TwigEnvironment $twig): CoreExtension
    {
        return $twig->getExtension(CoreExtension::class);
    }
}
