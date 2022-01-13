<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\Check;

use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use Roave\BackwardCompatibility\Change;
use Roave\BackwardCompatibility\Changes;
use Shopware\RoaveBackwardCompatibility\SimpleAnnotation\ParseConfig;
use Shopware\RoaveBackwardCompatibility\SimpleAnnotation\SimpleAnnotationParser;

class AnnotationDiff
{
    private const ANNOTATION_MAPPING = [
        'Route' => [
            'index' => 'name',
            'numeric' => ['path', 'name'],
            'check' => [
                'name' => 'string',
                'path' => 'string',
                'methods' => 'array'
            ]
        ],
        'RouteScope' => [
            'index' => 'scopes',
            'numeric' => ['scopes'],
            'check' => [
                'scopes' => 'array',
            ]
        ],
        'LoginRequired' => [
            'index' => 'unknown',
            'numeric' => ['allowGuest'],
            'check' => [
                'allowGuest' => 'string',
            ]
        ],
        'ContextTokenRequired' => [
            'index' => 'unknown',
            'numeric' => ['required'],
            'check' => [
                'required' => 'string',
            ]
        ],
        'Entity' => [
            'index' => 'unknown',
            'numeric' => ['value'],
            'check' => [
                'value' => 'string',
            ]
        ],
        'Since' => [
            'index' => 'unknown',
            'numeric' => ['value'],
            'check' => [
                'value' => 'string',
            ],
        ],
        'Acl' => [
            'index' => 'unknown',
            'numeric' => ['privileges'],
            'check' => [
                'privileges' => 'array',
            ],
        ],
    ];

    public static function diff(string $identifier, string $before, string $after): Changes
    {
        $mapping = self::ANNOTATION_MAPPING;
        $factoryConfig = [];

        foreach (array_keys($mapping) as $name) {
            $factoryConfig[$name] = Generic::class;
        }

        $docblock = DocBlockFactory::createInstance($factoryConfig);

        // If previous method didnt had any doc comment ignore it
        if ($before === '') {
            return Changes::empty();
        }

        $before = $docblock->create($before);

        // Docblock Factory needs a filled string. If we removed the doc comment add a space
        if ($after === '') {
            $after = ' ';
        }

        $after = $docblock->create($after);

        foreach ($mapping as $name => $cfg) {
            $beforeTags = $before->getTagsByName($name);
            $afterTags = $after->getTagsByName($name);

            // Additions are okay, so skip it early
            if (\count($beforeTags) === 0) {
                continue;
            }

            $config = new ParseConfig($cfg['numeric']);

            if (\count($beforeTags) > 0) {
                $beforeParsed = self::convertList($beforeTags, $config, $cfg['index']);
                $afterParsed = self::convertList($afterTags, $config, $cfg['index']);

                foreach ($beforeParsed as $index => $beforeAnnotation) {
                    // Annotation has been completely removed
                    if (!isset($afterParsed[$index])) {
                        return Changes::fromList(Change::removed(
                            \sprintf('The annotation "%s" has been removed on "%s"', $name, $identifier),
                            true
                        ));
                    }

                    foreach ($cfg['check'] as $checkProperty => $compareType) {
                        // Ignore it's not defined before and after
                        if (!isset($beforeAnnotation[$checkProperty]) && !isset($afterParsed[$index][$checkProperty])) {
                            continue;
                        }

                        switch ($compareType) {
                            case 'string':
                                $beforeValue = $beforeAnnotation[$checkProperty] ?? '';
                                $afterValue = $afterParsed[$index][$checkProperty] ?? '';

                                if ($beforeValue !== $afterValue) {
                                    return Changes::fromList(Change::changed(
                                        \sprintf(
                                            'The annotation "%s" parameter "%s" has been changed on %s from "%s" to "%s"',
                                            $name,
                                            $checkProperty,
                                            $identifier,
                                            $beforeValue,
                                            $afterValue
                                        ),
                                        true
                                    ));
                                }

                                break;
                            case 'array':
                                $beforeValue = $beforeAnnotation[$checkProperty] ?? [];
                                $afterValue = $afterParsed[$index][$checkProperty] ?? [];

                                if (array_diff($beforeValue, $afterValue) !== []) {
                                    return Changes::fromList(Change::changed(
                                        \sprintf(
                                            'The annotation "%s" parameter "%s" has been changed on %s from "%s" to "%s"',
                                            $name,
                                            $checkProperty,
                                            $identifier,
                                            var_export($beforeValue, true),
                                            var_export($afterValue, true)
                                        ),
                                        true
                                    ));
                                }

                                break;
                            default:
                                throw new \RuntimeException('Unknown compare type ' . $compareType);
                        }
                    }
                }
            }
        }

        return Changes::empty();
    }

    private static function convertList(array $tags, ParseConfig $config, string $index): array
    {
        $result = [];

        /** @var BaseTag $tag */
        foreach ($tags as $tag) {
            preg_match('/^\(.*\)/Us', $tag->getDescription()->render(), $match);

            $parsed = SimpleAnnotationParser::parse($match[0], $config);

            $indexValue = $parsed[$index] ?? 'unknown';
            if (\is_array($indexValue)) {
                ksort($indexValue);
                $indexValue = hash('sha512', json_encode($indexValue));
            }

            $result[$indexValue] = $parsed;
        }

        return $result;
    }
}
