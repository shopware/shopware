<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\SimpleAnnotation;

class SimpleAnnotationParser
{
    private const NUMERIC_ARGUMENT = '/^"([^"]*)\"[\s,]+/';
    private const ARRAY_ARGUMENT = '/^{(?<args>.*)},/Us';
    private const NAMED_ARRAY_ARGUMENT = '/^(?<name>\w*)={(?<args>.*)},/Us';
    private const NAMED_ARGUMENT = '/^"?(?<name>\w*)"?[=:]\s*"?(?<value>.*)"?,/U';

    public static function parse(string $comment, ParseConfig $config, ?string $previousComment = null): array
    {
        $result = [];

        $index = 0;
        $text = trim(rtrim(ltrim($comment, '('), ')')) . ',';
        $max = 20;

        if ($text === ',') {
            return [];
        }

        while ($text !== '') {
            if (preg_match(self::NUMERIC_ARGUMENT, $text, $match)) {
                $text = self::cleanup($text, \strlen($match[0]));
                $parsed = trim($match[1], '"');

                if (isset($config->numericArgumentMapping[$index])) {
                    $result[$config->numericArgumentMapping[$index]] = $parsed;
                } else {
                    $result[] = $parsed;
                }
                ++$index;
            } elseif (preg_match(self::ARRAY_ARGUMENT, $text, $match)) {
                $text = self::cleanup($text, \strlen($match[0]));
                $parsed = self::parse(trim($match['args']), new ParseConfig([]), $comment);

                if (isset($config->numericArgumentMapping[$index])) {
                    $result[$config->numericArgumentMapping[$index]] = $parsed;
                } else {
                    $result[] = $parsed;
                }
                ++$index;
            } elseif (preg_match(self::NAMED_ARRAY_ARGUMENT, $text, $match)) {
                $text = self::cleanup($text, \strlen($match[0]));

                $result[$match['name']] = self::parse(trim($match['args']), new ParseConfig([]), $comment);
            } elseif (preg_match(self::NAMED_ARGUMENT, $text, $match)) {
                $text = self::cleanup($text, \strlen($match[0]));
                $value = trim($match['value']);

                if ($value[0] === '{') {
                    $value = trim(rtrim(ltrim($value, '{'), '}'));
                    $result[$match['name']] = self::parse($value, new ParseConfig([]), $comment);
                } else {
                    $result[$match['name']] = trim($match['value'], '" ');
                }
            }

            --$max;

            if ($max === 0) {
                throw new \RuntimeException(\sprintf('Cannot parse %s at point %s', $previousComment ?? $comment, $comment));
            }
        }

        return $result;
    }

    private static function cleanup(string $text, int $remove): string
    {
        return trim(ltrim(trim(substr($text, $remove)), ','));
    }
}
