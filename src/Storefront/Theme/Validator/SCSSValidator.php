<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Validator;

use ScssPhp\ScssPhp\Colors;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\AbstractScssCompiler;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\Exception\ThemeException;

#[Package('framework')]
class SCSSValidator
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $customAllowedRegex
     */
    public static function validate(AbstractScssCompiler $compiler, array $data, array $customAllowedRegex = [], bool $sanitize = false): mixed
    {
        // Empty values are allowed and will be set as a "null" value in SCSS.
        // In addition, 0 or "0" can be valid values, for example for setting margins.
        if (!\array_key_exists('value', $data)
            || !isset($data['value'])
            || $data['value'] === ''
            || ($data['value'] === false && !\in_array($data['type'], ['checkbox', 'switch', 'boolean', 'bool'], true))) {
            return null;
        }

        if (\is_string($data['value']) && self::validateCustom($data['value'], $customAllowedRegex)) {
            return $data['value'];
        }

        /**
         * The types textarea, url and media are not validated, because
         * the textarea and url fields are wrapped as strings in the SCSS compiler,
         * and the media field is just using the media url as a string.
         */
        return match ($data['type'] ?? 'text') {
            'checkbox', 'switch' => self::validateTypeCheckbox($data['value']),
            'color' => self::validateTypeColor($compiler, $sanitize, $data['value'], $data['name'] ?? 'undefined', $data['type'] ?? 'undefined'),
            'fontFamily' => self::validateFontFamily($compiler, $sanitize, $data['value'], $data['name'] ?? 'undefined', $data['type'] ?? 'undefined'),
            'text' => self::validateTypeText($compiler, $sanitize, $data['value'], $data['name'] ?? 'undefined', $data['type'] ?? 'undefined'),
            default => $data['value'],
        };
    }

    /**
     * @param array<int, string> $customAllowedRegex
     */
    private static function validateCustom(string $value, array $customAllowedRegex): bool
    {
        foreach ($customAllowedRegex as $regex) {
            preg_match('/' . $regex . '/i', $value, $parsed);
            if (isset($parsed[0]) && !empty($parsed[0])) {
                return true;
            }
        }

        return false;
    }

    private static function validateTypeCheckbox(mixed $value): mixed
    {
        if (!\is_bool($value) && $value !== 1 && $value !== 0) {
            return 'true';
        }

        return $value;
    }

    private static function validateTypeColor(AbstractScssCompiler $compiler, bool $sanitize, mixed $value, string $name, string $type): mixed
    {
        try {
            $css = self::initVariables($value, '#000') . \PHP_EOL;
            $css .= 'body{background-color:' . $value . ';color: darken(' . $value . ', 10%)}';

            $parsed = $compiler->compileString(
                new CompilerConfiguration(
                    ['outputStyle' => OutputStyle::COMPRESSED, 'importPaths' => []]
                ),
                $css
            );

            preg_match('/body\{background-color:(.*);/i', $parsed, $parsedValue);

            /**
             * If the parsed value is not a valid color, throw an exception.
             */
            if (!isset($parsedValue[1]) || !self::isValidColorName($parsedValue[1])) {
                throw ThemeException::InvalidScssValue($value, $type, $name);
            }

            /**
             * The SCSS compiler has an issue and compiles invalid hsl and rgb colors to hex.
             * Therefore the compiler does not crash, and the parsed value is valid, but the original color is invalid.
             * This could lead to compiler crashes at a later stage, for example, when using the color in a mixin.
             */
            if ((str_starts_with($value, 'hsl') && !self::isHSL($value))
                || (str_starts_with($value, 'rgb') && !self::isRGB($value))) {
                throw ThemeException::InvalidScssValue($value, $type, $name);
            }

            return $value;
        } catch (\Throwable $exception) {
            /**
             * If the color could not be compiled at all, throw an exception.
             */
            if ($sanitize !== true) {
                throw ThemeException::InvalidScssValue($value, $type, $name);
            }

            return '#ffffff00';
        }
    }

    private static function validateFontFamily(AbstractScssCompiler $compiler, bool $sanitize, mixed $value, string $name, string $type): mixed
    {
        $value = str_replace('\'', '"', $value);
        $css = 'body{font-family:' . $value . ';--my-font: ' . $value . '}';
        try {
            $parsed = $compiler->compileString(
                new CompilerConfiguration(
                    ['outputStyle' => OutputStyle::COMPRESSED, 'importPaths' => []]
                ),
                $css
            );
            preg_match('/body\{font-family:(.*);/i', $parsed, $parsedValue);

            if (
                isset($parsedValue[1])
                && \is_string($parsedValue[1])
            ) {
                return $value;
            }

            if ($sanitize !== true) {
                throw ThemeException::InvalidScssValue($value, $type, $name);
            }

            return 'inherit';
        } catch (\Throwable $exception) {
            if ($sanitize !== true) {
                throw ThemeException::InvalidScssValue($value, $type, $name);
            }

            return 'inherit';
        }
    }

    private static function validateTypeText(AbstractScssCompiler $compiler, bool $sanitize, mixed $value, string $name, string $type): mixed
    {
        $css = '$' . $name . ': ' . $value . ';';

        try {
            $compiler->compileString(
                new CompilerConfiguration(
                    ['outputStyle' => OutputStyle::COMPRESSED, 'importPaths' => []]
                ),
                $css
            );

            return $value;
        } catch (\Throwable $exception) {
            if ($sanitize !== true) {
                throw ThemeException::InvalidScssValue(addslashes($value), $type, $name);
            }

            return 'inherit';
        }
    }

    private static function isValidColorName(mixed $value): bool
    {
        return (str_starts_with($value, '#') && self::isHex(substr($value, 1)))
            || (str_starts_with($value, 'hsl') && self::isHSL($value))
            || (str_starts_with($value, 'rgb') && self::isRGB($value))
            || (!str_starts_with($value, '#') && Colors::colorNameToRGBa($value) !== null);
    }

    private static function initVariables(string $value, string $varVal): string
    {
        $initString = '';
        $vars = self::extractSCSSvars($value);
        foreach ($vars as $var) {
            $initString .= \PHP_EOL . $var . ': ' . $varVal . ';';
        }

        return $initString;
    }

    /**
     * @return array<int, string>
     */
    private static function extractSCSSvars(string $value): array
    {
        $matches = [];
        $vars = [];
        preg_match_all('/.*?(\$(?![0-9])(?:[a-zA-Z0-9-_]|(?:\\[!"#$%&\'\(\)*+,.\/:;<=>?@\[\]^{|}~]))+)/', $value, $matches);
        foreach ($matches[1] as $match) {
            $vars[] = $match;
        }

        return $vars;
    }

    private static function isHex(string $hexCode): bool
    {
        preg_match('/^[a-f0-9]*$/i', $hexCode, $parsed);

        return isset($parsed[0]) && $parsed[0] === $hexCode;
    }

    /**
     * Validates if the given HSL code is valid.
     *
     * It supports both classic and modern syntax.
     * Will forward to isHSLA for classic syntax with 'hsla'.
     */
    private static function isHSL(string $hslCode): bool
    {
        if (!str_starts_with($hslCode, 'hsl')) {
            return false;
        }

        if (str_starts_with($hslCode, 'hsla')) {
            return self::isHSLA($hslCode);
        }

        $hue = '(?:360(?:\.0+)?|3[0-5]\d(?:\.\d+)?|[12]\d{2}(?:\.\d+)?|[1-9]?\d(?:\.\d+)?)';
        $percent = '(?:100|[1-9]?\d)%';
        $alpha = '(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%)';

        // Modern: hsl(h s% l% / a?)  (spaces; optional / alpha)
        $patternModern = '/^hsl\(\s*' . $hue . '(?:deg)?\s+' . $percent . '\s+' . $percent . '(?:\s*\/\s*' . $alpha . ')?\s*\)$/i';

        // Legacy: hsl(h, s%, l%)  (commas; no alpha)
        $patternLegacy = '/^hsl\(\s*' . $hue . '(?:deg)?\s*,\s*' . $percent . '\s*,\s*' . $percent . '\s*\)$/i';

        return preg_match($patternModern, $hslCode) === 1 || preg_match($patternLegacy, $hslCode) === 1;
    }

    /**
     * Validates if the given HSLA code is valid.
     */
    private static function isHSLA(string $hslaCode): bool
    {
        $hue = '(?:360(?:\.0+)?|3[0-5]\d(?:\.\d+)?|[12]\d{2}(?:\.\d+)?|[1-9]?\d(?:\.\d+)?)';
        $percent = '(?:100|[1-9]?\d)%';
        $alpha = '(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%)';

        // Legacy HSLA: hsla(h, s%, l%, a)
        $patternHsla = '/^hsla\(\s*' . $hue . '(?:deg)?\s*,\s*' . $percent . '\s*,\s*' . $percent . '\s*,\s*' . $alpha . '\s*\)$/i';

        return preg_match($patternHsla, $hslaCode) === 1;
    }

    /**
     * Validates if the given RGB code is valid.
     *
     * It supports both classic and modern syntax.
     * Will forward to isRGBA for classic syntax with 'rgba'.
     */
    private static function isRGB(string $rgbCode): bool
    {
        if (!str_starts_with($rgbCode, 'rgb')) {
            return false;
        }

        if (str_starts_with($rgbCode, 'rgba')) {
            return self::isRGBA($rgbCode);
        }

        $patternRgb = '/^rgb\(\s*(?:(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)\s+(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)\s+(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)(?:\s*\/\s*(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%))?|(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%))\s*\)$/i';

        return preg_match($patternRgb, $rgbCode) === 1;
    }

    /**
     * Validates if the given RGBA code is valid.
     */
    private static function isRGBA(string $rgbaCode): bool
    {
        $patternRgba = '/^rgba\(\s*(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)\s*,\s*(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%)\s*\)$/i';

        return preg_match($patternRgba, $rgbaCode) === 1;
    }
}
