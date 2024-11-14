<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\Validator\SCSSValidator;

/**
 * @internal
 */
#[Package('storefront')]
#[CoversClass(SCSSValidator::class)]
class SCSSValidatorTest extends TestCase
{
    /**
     * @param array<string, string> $data
     */
    #[DataProvider('sanitizeDataProvider')]
    public function testValidateSanitize(array $data, string $expected): void
    {
        $returned = SCSSValidator::validate(new ScssPhpCompiler(), $data, [], true);

        static::assertSame($expected, $returned);
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('validateDataProvider')]
    public function testValidateNoSanitize(array $data, string $expected, bool $throwsException = false): void
    {
        if ($throwsException) {
            self::expectException(ThemeException::class);
        }

        $returned = SCSSValidator::validate(new ScssPhpCompiler(), $data);

        static::assertSame($expected, $returned);
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('validateDataProviderRegex')]
    public function testValidateNoSanitizeRegex(array $data, string $expected, bool $throwsException = false): void
    {
        if ($throwsException) {
            self::expectException(ThemeException::class);
        }

        $returned = SCSSValidator::validate(new ScssPhpCompiler(), $data, ['^\$.*']);

        static::assertSame($expected, $returned);
    }

    public static function sanitizeDataProvider(): \Generator
    {
        // correct
        yield 'color correct hex 3' => [
            [
                'type' => 'color',
                'value' => '#fff',
            ],
            '#fff',
        ];
        yield 'fontFamily correct' => [
            [
                'type' => 'fontFamily',
                'value' => 'Arial',
            ],
            'Arial',
        ];
        yield 'fontFamily correct with Inter' => [
            [
                'type' => 'fontFamily',
                'value' => '\'Inter\', Sans-serif',
            ],
            '"Inter", Sans-serif',
        ];
        yield 'color correct hex 4' => [
            [
                'type' => 'color',
                'value' => '#fff0',
            ],
            '#fff0',
        ];
        yield 'color correct hex 6' => [
            [
                'type' => 'color',
                'value' => '#fff000',
            ],
            '#fff000',
        ];
        yield 'color correct hex 7' => [
            [
                'type' => 'color',
                'value' => '#fff0000',
            ],
            '#ffffff00',
        ];
        yield 'color correct hex 8' => [
            [
                'type' => 'color',
                'value' => '#fff00000',
            ],
            '#fff00000',
        ];
        yield 'color correct name' => [
            [
                'type' => 'color',
                'value' => 'indigo',
            ],
            'indigo',
        ];
        yield 'color correct hsl 3' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 4)',
            ],
            '#0b0a0a',
        ];
        yield 'color correct hsl 4' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 4, 0.5)',
            ],
            'rgba(11, 10, 10, 0.5)',
        ];
        yield 'color correct rgb 3' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 4)',
            ],
            '#040404',
        ];
        yield 'color correct rgb 5' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 4, 0.5)',
            ],
            'rgba(4, 4, 4, 0.5)',
        ];
        yield 'color correct hsla 3' => [
            [
                'type' => 'color',
                'value' => 'hsla(4, 4, 4, 0.5)',
            ],
            'rgba(11, 10, 10, 0.5)',
        ];
        yield 'color correct hsla 4' => [
            [
                'type' => 'color',
                'value' => 'hsla(4, 4, 4, 0.5)',
            ],
            'rgba(11, 10, 10, 0.5)',
        ];
        yield 'color correct rgba 3' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 4)',
            ],
            '#040404',
        ];
        yield 'color correct rgba 4' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 4, 0.5)',
            ],
            'rgba(4, 4, 4, 0.5)',
        ];
        // incorrect but valid(no error in compilation)
        yield 'color incorrect but valid hex 3' => [
            [
                'type' => 'color',
                'value' => '#ffg',
            ],
            '#ffffff00',
        ];
        yield 'color incorrect but valid hex 4' => [
            [
                'type' => 'color',
                'value' => '#ffg0',
            ],
            '#ffffff00',
        ];
        yield 'color incorrect but valid hex 6' => [
            [
                'type' => 'color',
                'value' => '#ffg000',
            ],
            '#ffffff00',
        ];
        yield 'color incorrect but valid hex 7' => [
            [
                'type' => 'color',
                'value' => '#ffg0000',
            ],
            '#ffffff00',
        ];
        yield 'color incorrect but valid hex 8' => [
            [
                'type' => 'color',
                'value' => '#ffg00000',
            ],
            '#ffffff00',
        ];
        yield 'color incorrect but valid hsl 3' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 500)',
            ],
            'white',
        ];
        yield 'color incorrect but valid hsl 4' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 4, 5)',
            ],
            '#0b0a0a',
        ];
        yield 'color incorrect but valid rgb 3' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 500)',
            ],
            '#0404ff',
        ];
        yield 'color incorrect but valid rgb 5' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 4, 5)',
            ],
            '#040404',
        ];
        yield 'color incorrect hsla 3' => [
            [
                'type' => 'color',
                'value' => 'hsla(4, 4, 5)',
            ],
            '#0d0c0c',
        ];
        yield 'color incorrect but valid hsla 4' => [
            [
                'type' => 'color',
                'value' => 'hsla(40, 4, 4, 5)',
            ],
            '#0b0a0a',
        ];
        yield 'color incorrect rgba 3' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 500)',
            ],
            '#0404ff',
        ];
        yield 'color incorrect but valid rgba 4' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 4, 5)',
            ],
            '#040404',
        ];
        // Incorrect and sanitized
        yield 'color incorrect and sanitized name' => [
            [
                'type' => 'color',
                'value' => 'lilaschwarzgepunktet',
            ],
            '#ffffff00',
        ];
        yield 'color incorrect and sanitized hex 5' => [
            [
                'type' => 'color',
                'value' => '#ffg00',
            ],
            '#ffffff00',
        ];
        yield 'fontFamily incorrect and sanitized' => [
            [
                'type' => 'fontFamily',
                'value' => 'Arial%&$',
            ],
            'inherit',
        ];
        yield 'col incorrect and sanitized' => [
            [
                'type' => 'color',
                'value' => '#FFG',
            ],
            '#ffffff00',
        ];
    }

    public static function validateDataProvider(): \Generator
    {
        // correct
        yield 'color correct hex 3' => [
            [
                'type' => 'color',
                'value' => '#fff',
            ],
            '#fff',
        ];
        yield 'color correct darken' => [
            [
                'type' => 'color',
                'value' => 'darken($myColor, 15%)',
            ],
            'black',
        ];
        yield 'color correct lighten' => [
            [
                'type' => 'color',
                'value' => 'lighten($myColor, 15%)',
            ],
            '#262626',
        ];
        yield 'fontFamily correct' => [
            [
                'type' => 'fontFamily',
                'value' => 'Arial',
            ],
            'Arial',
        ];
        yield 'fontFamily correct with Inter' => [
            [
                'type' => 'fontFamily',
                'value' => '\'Inter\', Sans-serif',
            ],
            '"Inter", Sans-serif',
        ];
        yield 'color correct hex 4' => [
            [
                'type' => 'color',
                'value' => '#fff0',
            ],
            '#fff0',
        ];
        yield 'color correct hex 6' => [
            [
                'type' => 'color',
                'value' => '#fff000',
            ],
            '#fff000',
        ];
        yield 'color incorrect he 7' => [
            [
                'type' => 'color',
                'value' => '#fff0000',
            ],
            '',
            true,
        ];
        yield 'color correct hex 8' => [
            [
                'type' => 'color',
                'value' => '#fff00000',
            ],
            '#fff00000',
        ];
        yield 'color correct name' => [
            [
                'type' => 'color',
                'value' => 'indigo',
            ],
            'indigo',
        ];
        yield 'color correct hsl 3' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 4)',
            ],
            '#0b0a0a',
        ];
        yield 'color correct hsl 4' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 4, 0.5)',
            ],
            'rgba(11, 10, 10, 0.5)',
        ];
        yield 'color correct rgb 3' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 4)',
            ],
            '#040404',
        ];
        yield 'color correct rgb 5' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 4, 0.5)',
            ],
            'rgba(4, 4, 4, 0.5)',
        ];
        yield 'color correct hsla 3' => [
            [
                'type' => 'color',
                'value' => 'hsla(4, 4, 4, 0.5)',
            ],
            'rgba(11, 10, 10, 0.5)',
        ];
        yield 'color correct hsla 4' => [
            [
                'type' => 'color',
                'value' => 'hsla(4, 4, 4, 0.5)',
            ],
            'rgba(11, 10, 10, 0.5)',
        ];
        yield 'color correct rgba 3' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 4)',
            ],
            '#040404',
        ];
        yield 'color correct rgba 4' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 4, 0.5)',
            ],
            'rgba(4, 4, 4, 0.5)',
        ];
        yield 'color incorrect hex 3' => [
            [
                'type' => 'color',
                'value' => '#ffg',
            ],
            '',
            true,
        ];
        yield 'color custom value' => [
            [
                'type' => 'color',
                'value' => 'foo(#fff)',
            ],
            '',
            true,
        ];
        yield 'color incorrect hex 4' => [
            [
                'type' => 'color',
                'value' => '#ffg0',
            ],
            '',
            true,
        ];
        yield 'color incorrect hex 6' => [
            [
                'type' => 'color',
                'value' => '#ffg000',
            ],
            '',
            true,
        ];
        yield 'color incorrect hex 7' => [
            [
                'type' => 'color',
                'value' => '#ffg0000',
            ],
            '',
            true,
        ];
        yield 'color incorrect hex 8' => [
            [
                'type' => 'color',
                'value' => '#ffg00000',
            ],
            '',
            true,
        ];
        yield 'color incorrect but valid hsl 3' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 500)',
            ],
            'white',
        ];
        yield 'color incorrect but valid hsl 4' => [
            [
                'type' => 'color',
                'value' => 'hsl(4, 4, 4, 5)',
            ],
            '#0b0a0a',
        ];
        yield 'color incorrect but valid rgb 3' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 500)',
            ],
            '#0404ff',
        ];
        yield 'color incorrect but valid rgb 5' => [
            [
                'type' => 'color',
                'value' => 'rgb(4, 4, 4, 5)',
            ],
            '#040404',
        ];
        yield 'color incorrect hsla 3' => [
            [
                'type' => 'color',
                'value' => 'hsla(4, 4, 5)',
            ],
            '#0d0c0c',
        ];
        yield 'color incorrect hsla 4 will be sanitized to hex' => [
            [
                'type' => 'color',
                'value' => 'hsla(40, 4, 4, 5)',
            ],
            '#0b0a0a',
        ];
        yield 'color incorrect rgba 3 will be sanitized to hex' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 500)',
            ],
            '#0404ff',
        ];
        yield 'color incorrect rgba 4 but sanitized to hex' => [
            [
                'type' => 'color',
                'value' => 'rgba(4, 4, 4, 5)',
            ],
            '#040404',
        ];
        // Incorrect and sanitized
        yield 'color incorrect name' => [
            [
                'type' => 'color',
                'value' => 'lilaschwarzgepunktet',
            ],
            '',
            true,
        ];
        yield 'color incorrect hex 5' => [
            [
                'type' => 'color',
                'value' => '#ffg00',
            ],
            '',
            true,
        ];
        yield 'fontFamily incorrect' => [
            [
                'type' => 'fontFamily',
                'value' => 'Arial%&$',
            ],
            '',
            true,
        ];
        yield 'color incorrect' => [
            [
                'type' => 'color',
                'value' => '#FFG',
            ],
            '',
            true,
        ];
    }

    public static function validateDataProviderRegex(): \Generator
    {
        // correct
        yield 'color correct hex 3' => [
            [
                'type' => 'color',
                'value' => '#fff',
            ],
            '#fff',
        ];
        yield 'color regex SASS variable $ allow list' => [
            [
                'type' => 'color',
                'value' => '$test',
            ],
            '$test',
        ];
        // Incorrect and sanitized
        yield 'color incorrect name' => [
            [
                'type' => 'color',
                'value' => 'lilaschwarzgepunktet',
            ],
            '',
            true,
        ];
        yield 'color regex --' => [
            [
                'type' => 'color',
                'value' => '--test',
            ],
            '',
            true,
        ];
        yield 'color regex var' => [
            [
                'type' => 'color',
                'value' => 'var(--test-test)',
            ],
            '',
            true,
        ];
        yield 'color regex custom' => [
            [
                'type' => 'color',
                'value' => 'foo(#fff)',
            ],
            '',
            true,
        ];
    }
}
