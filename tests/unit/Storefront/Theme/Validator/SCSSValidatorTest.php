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
#[Package('framework')]
#[CoversClass(SCSSValidator::class)]
class SCSSValidatorTest extends TestCase
{
    /**
     * @param array<string, string> $data
     */
    #[DataProvider('sanitizeDataProvider')]
    public function testValidateSanitize(array $data, string|bool|null $expected): void
    {
        $returned = SCSSValidator::validate(new ScssPhpCompiler(), $data, [], true);

        static::assertSame($expected, $returned);
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('validateDataProvider')]
    public function testValidateNoSanitize(array $data, string|bool|null $expected, bool $throwsException = false): void
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
    public function testValidateNoSanitizeRegex(array $data, string|bool|null $expected, bool $throwsException = false): void
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
        yield 'text correct' => [
            [
                'type' => 'text',
                'value' => '2px solid #000',
            ],
            '2px solid #000',
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
        yield 'color correct hsl classic' => [
            [
                'type' => 'color',
                'value' => 'hsl(120, 50%, 50%)',
            ],
            'hsl(120, 50%, 50%)',
        ];
        yield 'color correct hsl modern' => [
            [
                'type' => 'color',
                'value' => 'hsl(120 50% 50%)',
            ],
            'hsl(120 50% 50%)',
        ];
        yield 'color correct hsl modern with alpha' => [
            [
                'type' => 'color',
                'value' => 'hsl(120 50% 50% / 0.8)',
            ],
            'hsl(120 50% 50% / 0.8)',
        ];
        yield 'color correct hsla' => [
            [
                'type' => 'color',
                'value' => 'hsla(120, 50%, 50%, 0.8)',
            ],
            'hsla(120, 50%, 50%, 0.8)',
        ];
        yield 'color correct rgb classic' => [
            [
                'type' => 'color',
                'value' => 'rgb(255, 0, 0)',
            ],
            'rgb(255, 0, 0)',
        ];
        yield 'color correct rgb modern' => [
            [
                'type' => 'color',
                'value' => 'rgb(255 0 0)',
            ],
            'rgb(255 0 0)',
        ];
        yield 'color correct rgb modern with alpha' => [
            [
                'type' => 'color',
                'value' => 'rgb(255 0 0 / 0.5)',
            ],
            'rgb(255 0 0 / 0.5)',
        ];
        yield 'color correct rgba' => [
            [
                'type' => 'color',
                'value' => 'rgba(255, 0, 0, 0.5)',
            ],
            'rgba(255, 0, 0, 0.5)',
        ];
        // Empty values (are valid and will be set to null)
        yield 'color empty' => [
            [
                'type' => 'color',
                'value' => '',
            ],
            null,
        ];
        yield 'color value missing' => [
            [
                'type' => 'color',
            ],
            null,
        ];
        yield 'font family empty' => [
            [
                'type' => 'fontFamily',
                'value' => '',
            ],
            null,
        ];
        yield 'text empty' => [
            [
                'type' => 'text',
                'value' => '',
            ],
            null,
        ];
        // Boolean values
        yield 'checkbox true' => [
            [
                'type' => 'checkbox',
                'value' => true,
            ],
            true,
        ];
        yield 'switch true' => [
            [
                'type' => 'switch',
                'value' => true,
            ],
            true,
        ];
        yield 'checkbox false' => [
            [
                'type' => 'checkbox',
                'value' => false,
            ],
            false,
        ];
        yield 'switch false' => [
            [
                'type' => 'switch',
                'value' => false,
            ],
            false,
        ];
        // Zero values
        yield 'color with "0" value is sanitized' => [
            [
                'type' => 'color',
                'value' => '0',
            ],
            '#ffffff00',
        ];
        yield 'color with 0 value is sanitized' => [
            [
                'type' => 'color',
                'value' => 0,
            ],
            '#ffffff00',
        ];
        yield 'text with "0" value is not sanitized' => [
            [
                'type' => 'text',
                'value' => '0',
            ],
            '0',
        ];
        // incorrect but valid (no error in compilation)
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
        yield 'hsl incorrect and sanitized' => [
            [
                'type' => 'color',
                'value' => 'hsl(400, 50%, 50%)',
            ],
            '#ffffff00',
        ];
        yield 'hsla incorrect and sanitized' => [
            [
                'type' => 'color',
                'value' => 'hsla(400, 50%, 50%, 0.8)',
            ],
            '#ffffff00',
        ];
        yield 'rgb incorrect and sanitized' => [
            [
                'type' => 'color',
                'value' => 'rgb(300, 0, 0)',
            ],
            '#ffffff00',
        ];
        yield 'rgba incorrect and sanitized' => [
            [
                'type' => 'color',
                'value' => 'rgba(255, 0, 0, 1.5)',
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
        yield 'text incorrect and sanitized name' => [
            [
                'type' => 'text',
                'value' => '"§"$/)(!"§&)=}[];"{',
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
        yield 'color correct SCSS function darken' => [
            [
                'type' => 'color',
                'value' => 'darken($myColor, 15%)',
            ],
            'darken($myColor, 15%)',
        ];
        yield 'color correct SCSS function lighten' => [
            [
                'type' => 'color',
                'value' => 'lighten($myColor, 15%)',
            ],
            'lighten($myColor, 15%)',
        ];
        yield 'color correct darken with rgb' => [
            [
                'type' => 'color',
                'value' => 'darken(rgb(255, 0, 0), 15%)',
            ],
            'darken(rgb(255, 0, 0), 15%)',
        ];
        yield 'color correct lighten with rgb' => [
            [
                'type' => 'color',
                'value' => 'lighten(rgb(255, 0, 0), 15%)',
            ],
            'lighten(rgb(255, 0, 0), 15%)',
        ];
        yield 'color correct darken with hsl' => [
            [
                'type' => 'color',
                'value' => 'darken(hsl(120, 50%, 50%), 15%)',
            ],
            'darken(hsl(120, 50%, 50%), 15%)',
        ];
        yield 'color correct lighten with hsl' => [
            [
                'type' => 'color',
                'value' => 'lighten(hsl(120, 50%, 50%), 15%)',
            ],
            'lighten(hsl(120, 50%, 50%), 15%)',
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
        yield 'text correct' => [
            [
                'type' => 'text',
                'value' => '2px solid #000',
            ],
            '2px solid #000',
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
        yield 'color correct hsl classic' => [
            [
                'type' => 'color',
                'value' => 'hsl(120, 50%, 50%)',
            ],
            'hsl(120, 50%, 50%)',
        ];
        yield 'color correct hsl modern' => [
            [
                'type' => 'color',
                'value' => 'hsl(120 50% 50%)',
            ],
            'hsl(120 50% 50%)',
        ];
        yield 'color correct hsl modern with alpha' => [
            [
                'type' => 'color',
                'value' => 'hsl(120 50% 50% / 0.8)',
            ],
            'hsl(120 50% 50% / 0.8)',
        ];
        yield 'color correct hsla' => [
            [
                'type' => 'color',
                'value' => 'hsla(120, 50%, 50%, 0.8)',
            ],
            'hsla(120, 50%, 50%, 0.8)',
        ];
        yield 'color correct rgb classic' => [
            [
                'type' => 'color',
                'value' => 'rgb(255, 0, 0)',
            ],
            'rgb(255, 0, 0)',
        ];
        yield 'color correct rgb modern' => [
            [
                'type' => 'color',
                'value' => 'rgb(255 0 0)',
            ],
            'rgb(255 0 0)',
        ];
        yield 'color correct rgb modern with alpha' => [
            [
                'type' => 'color',
                'value' => 'rgb(255 0 0 / 0.5)',
            ],
            'rgb(255 0 0 / 0.5)',
        ];
        yield 'color correct rgba' => [
            [
                'type' => 'color',
                'value' => 'rgba(255, 0, 0, 0.5)',
            ],
            'rgba(255, 0, 0, 0.5)',
        ];
        // Boolean values
        yield 'checkbox true' => [
            [
                'type' => 'checkbox',
                'value' => true,
            ],
            true,
        ];
        yield 'switch true' => [
            [
                'type' => 'switch',
                'value' => true,
            ],
            true,
        ];
        yield 'checkbox false' => [
            [
                'type' => 'checkbox',
                'value' => false,
            ],
            false,
        ];
        yield 'switch false' => [
            [
                'type' => 'switch',
                'value' => false,
            ],
            false,
        ];
        // Empty values (are valid and will be set to null)
        yield 'color empty' => [
            [
                'type' => 'color',
                'value' => '',
            ],
            null,
        ];
        yield 'color value missing' => [
            [
                'type' => 'color',
            ],
            null,
        ];
        yield 'font family empty' => [
            [
                'type' => 'fontFamily',
                'value' => '',
            ],
            null,
        ];
        yield 'text empty' => [
            [
                'type' => 'text',
                'value' => '',
            ],
            null,
        ];
        // Zero values
        yield 'color with "0" value is not valid' => [
            [
                'type' => 'color',
                'value' => '0',
            ],
            '',
            true,
        ];
        yield 'color with 0 value is not valid' => [
            [
                'type' => 'color',
                'value' => 0,
            ],
            '',
            true,
        ];
        yield 'text with "0" value is valid' => [
            [
                'type' => 'text',
                'value' => '0',
            ],
            '0',
        ];
        // Incorrect and throws exception
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
        yield 'text incorrect' => [
            [
                'type' => 'text',
                'value' => '"§"$/)(!"§&)=}[];"{',
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
        yield 'color incorrect hsl classic' => [
            [
                'type' => 'color',
                'value' => 'hsl(400, 50%, 50%)',
            ],
            '',
            true,
        ];
        yield 'color incorrect hsl modern' => [
            [
                'type' => 'color',
                'value' => 'hsl(400 50% 50%)',
            ],
            '',
            true,
        ];
        yield 'color incorrect hsl modern with alpha' => [
            [
                'type' => 'color',
                'value' => 'hsl(400 50% 50% / 0.8)',
            ],
            '',
            true,
        ];
        yield 'color incorrect hsla' => [
            [
                'type' => 'color',
                'value' => 'hsla(400, 50%, 50%, 0.8)',
            ],
            '',
            true,
        ];
        yield 'color incorrect rgb classic' => [
            [
                'type' => 'color',
                'value' => 'rgb(300, 0, 0)',
            ],
            '',
            true,
        ];
        yield 'color incorrect rgb modern' => [
            [
                'type' => 'color',
                'value' => 'rgb(300 0 0)',
            ],
            '',
            true,
        ];
        yield 'color incorrect rgba' => [
            [
                'type' => 'color',
                'value' => 'rgba(255, 0, 0, 1.5)',
            ],
            '',
            true,
        ];
        yield 'color incorrect SCSS function' => [
            [
                'type' => 'color',
                'value' => 'darken(foo, 15%)',
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
        yield 'color incorrect darken with rgb' => [
            [
                'type' => 'color',
                'value' => 'darken(rgb(300, 0, 0, 15%)',
            ],
            '',
            true,
        ];
        yield 'color incorrect lighten with rgb' => [
            [
                'type' => 'color',
                'value' => 'lighten(rgb(300, 0, 0, 15%)',
            ],
            '',
            true,
        ];
        yield 'color incorrect darken with hsl' => [
            [
                'type' => 'color',
                'value' => 'darken(hsl(400, 50%, 50%, 15%)',
            ],
            '',
            true,
        ];
        yield 'color incorrect lighten with hsl' => [
            [
                'type' => 'color',
                'value' => 'lighten(hsl(400, 50%, 50%, 15%)',
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
