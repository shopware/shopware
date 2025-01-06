<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Validator;

use ScssPhp\ScssPhp\Colors;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\AbstractScssCompiler;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\Exception\ThemeException;

#[Package('storefront')]
class SCSSValidator
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $customAllowedRegex
     */
    public static function validate(AbstractScssCompiler $compiler, array $data, array $customAllowedRegex = [], bool $sanitize = false): mixed
    {
        try {
            if ($sanitize === true) {
                $onError = function () use ($data): void {
                    throw ThemeException::InvalidScssValue('', $data['type'] ?? 'undefined', $data['name'] ?? 'undefined');
                };

                set_error_handler($onError);
            }

            if (!isset($data['value']) && !$sanitize) {
                throw ThemeException::InvalidScssValue('', $data['type'] ?? 'undefined', $data['name'] ?? 'undefined');
            }

            if (!\array_key_exists('value', $data) || (empty($data['value']) && $data['value'] !== 0)) {
                $data['value'] = 'inherit';
            }

            if (\is_string($data['value']) && self::validateCustom($data['value'], $customAllowedRegex)) {
                return $data['value'];
            }

            switch ($data['type'] ?? 'text') {
                case 'checkbox':
                case 'switch':
                    return self::validateTypeCheckbox($compiler, $data['value']);
                case 'color':
                    return self::validateTypeColor($compiler, $sanitize, $data['value'], $data['name'] ?? 'undefined', $data['type'] ?? 'undefined');
                case 'fontFamily':
                    return self::validateFontFamily($compiler, $sanitize, $data['value'], $data['name'] ?? 'undefined', $data['type'] ?? 'undefined');
            }
        } finally {
            restore_error_handler();
        }

        return $data['value'];
    }

    private static function isHex(string $hexCode): bool
    {
        preg_match('/^[a-f0-9]*$/i', $hexCode, $parsed);

        return isset($parsed[0]) && $parsed[0] === $hexCode;
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

    private static function validateTypeCheckbox(AbstractScssCompiler $compiler, mixed $value): mixed
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
            $css .= 'body{background-color: ' . $value . ';color: darken(' . $value . ', 10%)}';
            $parsed = $compiler->compileString(new CompilerConfiguration(['outputStyle' => OutputStyle::COMPRESSED, 'importPaths' => []]), $css);
            preg_match('/body\{background-color:(.*);/i', $parsed, $parsedValue);

            if (
                !self::isValidColorName($value)
                || !isset($parsedValue[1])
                || $parsedValue[1] !== $value
            ) {
                if (
                    !empty($parsedValue[1])
                    && $parsedValue[1] !== $value
                ) {
                    return $parsedValue[1];
                }

                throw ThemeException::InvalidScssValue($value, $type, $name);
            }

            return $value;
        } catch (\Throwable $exception) {
            if ($sanitize !== true) {
                throw ThemeException::InvalidScssValue($value, $type, $name);
            }

            return '#ffffff00';
        }
    }

    private static function validateFontFamily(AbstractScssCompiler $compiler, bool $sanitize, mixed $value, string $name, string $type): mixed
    {
        $value = str_replace('\'', '"', $value);
        $css = 'body{font-family: ' . $value . ';--my-font: ' . $value . '}';
        try {
            $parsed = $compiler->compileString(new CompilerConfiguration(['outputStyle' => OutputStyle::COMPRESSED, 'importPaths' => []]), $css);
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

    private static function isValidColorName(mixed $value): bool
    {
        return (
            str_starts_with($value, '#')
            && self::isHex(substr($value, 1))
        ) || str_starts_with($value, 'rgb')
                || str_starts_with($value, 'rgba')
                || str_starts_with($value, 'hsl')
                || str_starts_with($value, 'hsla')
                || (
                    !str_starts_with($value, '#')
                    && Colors::colorNameToRGBa($value) !== null
                );
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
}
