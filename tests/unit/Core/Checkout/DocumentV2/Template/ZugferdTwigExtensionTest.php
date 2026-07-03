<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Template\ZugferdTwigExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdTwigExtension::class)]
class ZugferdTwigExtensionTest extends TestCase
{
    private Environment $twig;

    private ZugferdTwigExtension $extension;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([]));
        $this->extension = new ZugferdTwigExtension();

        $this->twig->addExtension($this->extension);
    }

    #[DataProvider('decimalProvider')]
    public function testZugferdDecimal(float|int|string|null $input, int $places, string $expected): void
    {
        static::assertSame(
            $expected,
            $this->extension->zugferdDecimal($input, $places)
        );
    }

    /**
     * @return iterable<string, array{input: float|int|string|null, places: int, expected: string}>
     */
    public static function decimalProvider(): iterable
    {
        yield 'two_decimals' => [
            'input' => 1.005,
            'places' => 2,
            'expected' => '1.01',
        ];

        yield 'rounds_down' => [
            'input' => 1.004,
            'places' => 2,
            'expected' => '1.00',
        ];

        yield 'zero' => [
            'input' => 0,
            'places' => 2,
            'expected' => '0.00',
        ];

        yield 'negative' => [
            'input' => -12.5,
            'places' => 2,
            'expected' => '-12.50',
        ];

        yield 'string_input' => [
            'input' => '3.14159',
            'places' => 2,
            'expected' => '3.14',
        ];

        yield 'null_input' => [
            'input' => null,
            'places' => 2,
            'expected' => '0.00',
        ];

        yield 'four_decimals' => [
            'input' => 1.23456,
            'places' => 4,
            'expected' => '1.2346',
        ];

        yield 'large_number' => [
            'input' => 1234567.89,
            'places' => 2,
            'expected' => '1234567.89',
        ];
    }

    public function testZugferdDecimalIsAvailableAsTwigFilter(): void
    {
        $this->twig->setLoader(new ArrayLoader([
            't' => '{{ v|zugferd_decimal }}',
        ]));

        static::assertSame(
            '19.95',
            $this->twig->render('t', ['v' => 19.95])
        );
    }

    #[DataProvider('dateProvider')]
    public function testZugferdDate102(\DateTimeInterface|string|null $input, string $expected): void
    {
        static::assertSame(
            $expected,
            $this->extension->zugferdDate102($input)
        );
    }

    /**
     * @return iterable<string, array{input: \DateTimeInterface|string|null, expected: string}>
     */
    public static function dateProvider(): iterable
    {
        yield 'datetime_immutable' => [
            'input' => new \DateTimeImmutable('2026-05-11T08:00:00+00:00'),
            'expected' => '20260511',
        ];

        yield 'datetime_mutable' => [
            'input' => new \DateTime('2026-12-31T23:59:00Z'),
            'expected' => '20261231',
        ];

        yield 'iso_string' => [
            'input' => '2026-01-01T00:00:00+00:00',
            'expected' => '20260101',
        ];

        yield 'storage_format' => [
            'input' => '2026-05-05 12:00:00.000',
            'expected' => '20260505',
        ];

        yield 'null' => [
            'input' => null,
            'expected' => '',
        ];
    }

    public function testZugferdDate102WrapsMalformedStringInDomainException(): void
    {
        static::expectException(DocumentV2Exception::class);
        static::expectExceptionMessageMatches('/zugferd_date_102/');

        $this->extension->zugferdDate102('not-a-date');
    }

    public function testZugferdDate102IsAvailableAsTwigFilter(): void
    {
        $this->twig->setLoader(new ArrayLoader([
            't' => '{{ v|zugferd_date_102 }}',
        ]));

        static::assertSame(
            '20260511',
            $this->twig->render('t', [
                'v' => new \DateTimeImmutable('2026-05-11T08:00:00+00:00'),
            ]),
        );
    }

    #[DataProvider('htmlAutoescapeProducesValidXmlEntitiesProvider')]
    public function testTwigHtmlAutoescapeProducesValidXmlEntities(string $input, string $expected): void
    {
        $this->twig->setLoader(new ArrayLoader([
            't' => '{% autoescape "html" %}{{ v }}{% endautoescape %}',
        ]));

        static::assertSame(
            $expected,
            $this->twig->render('t', ['v' => $input])
        );
    }

    /**
     * @return iterable<string, array{input: string, expected: string}>
     */
    public static function htmlAutoescapeProducesValidXmlEntitiesProvider(): iterable
    {
        yield 'ampersand' => [
            'input' => 'First & Second',
            'expected' => 'First &amp; Second',
        ];

        yield 'lt' => [
            'input' => '1 < 2',
            'expected' => '1 &lt; 2',
        ];

        yield 'gt' => [
            'input' => '2 > 1',
            'expected' => '2 &gt; 1',
        ];

        yield 'double' => [
            'input' => 'He said "hi"',
            'expected' => 'He said &quot;hi&quot;',
        ];

        yield 'single' => [
            'input' => 'it\'s',
            'expected' => 'it&#039;s',
        ];

        yield 'plain' => [
            'input' => 'hello',
            'expected' => 'hello',
        ];

        yield 'empty' => [
            'input' => '',
            'expected' => '',
        ];

        yield 'unicode' => [
            'input' => 'Schöppingen',
            'expected' => 'Schöppingen',
        ];
    }
}
