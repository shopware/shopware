<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\Check;

use Doctrine\Common\Annotations\DocParser;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use Roave\BackwardCompatibility\Change;
use Roave\BackwardCompatibility\Changes;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Component\Routing\Annotation\Route;
use function dirname;
use function get_class;
use function get_object_vars;
use function json_encode;
use function sprintf;
use function strtolower;

class AnnotationDiff
{
    private const ANNOTATION_MAPPING = [
        'Route' => [
            'class' => Route::class,
            'index' => 'name',
            'numeric' => ['path', 'name'],
            'check' => [
                'name' => 'string',
                'path' => 'string',
                'methods' => 'array'
            ]
        ],
        'Entity' => [
            'class' => Entity::class,
            'index' => 'unknown',
            'numeric' => ['value'],
            'check' => [
                'value' => 'string',
            ]
        ],
        'Since' => [
            'class' => Since::class,
            'index' => 'unknown',
            'numeric' => ['value'],
            'check' => [
                'value' => 'string',
            ],
        ],
    ];

    private static ?DocParser $docParser = null;

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

            if (\count($beforeTags) > 0) {
                $beforeParsed = self::convertList($beforeTags, $cfg['index']);
                $afterParsed = self::convertList($afterTags, $cfg['index']);

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

    private static function convertList(array $tags, string $index): array
    {
        $result = [];

        /** @var BaseTag $tag */
        foreach ($tags as $tag) {
            if (!isset(self::ANNOTATION_MAPPING[$tag->getName()])) {
                continue;
            }

            preg_match('/^\(.*\)/Us', $tag->getDescription()->render(), $match);

            $comment = sprintf('/** * @%s%s */', $tag->getName(), $match[0]);

            try {
                $parsedObject = self::getParser()->parse($comment)[0];

            } catch (\Throwable $e) {
                $comment = sprintf('/** * @%s%s */', $tag->getName(), $tag->getDescription()->render());
                $parsedObject = self::getParser()->parse($comment)[0];
            }

            $caller = \Closure::bind(static function (object $parsedObject) {
                return get_object_vars($parsedObject);
            }, null, get_class($parsedObject));

            $parsed = $caller($parsedObject);

            $indexValue = $parsed[$index] ?? 'unknown';
            if (\is_array($indexValue)) {
                ksort($indexValue);
                $indexValue = hash('sha512', json_encode($indexValue));
            }

            $result[$indexValue] = $parsed;
        }

        return $result;
    }

    private static function getParser(): DocParser
    {
        if (self::$docParser !== null) {
            return self::$docParser;
        }

        require_once dirname(__DIR__, 4) . '/vendor/symfony/routing/Annotation/Route.php';
        require_once dirname(__DIR__, 4) . '/vendor/sensio/framework-extra-bundle/src/Configuration/ConfigurationInterface.php';
        require_once dirname(__DIR__, 4) . '/vendor/sensio/framework-extra-bundle/src/Configuration/ConfigurationAnnotation.php';
        require_once dirname(__DIR__, 4) . '/src/Core/Framework/Routing/Annotation/Entity.php';
        require_once dirname(__DIR__, 4) . '/src/Core/Framework/Routing/Annotation/Since.php';

        self::$docParser = new DocParser();
        $mapping = [];

        foreach (self::ANNOTATION_MAPPING as $key => $config) {
            $mapping[strtolower($key)] = $config['class'];
            $mapping[strtolower($config['class'])] = $config['class'];
        }

        self::$docParser->setImports($mapping);

        return self::$docParser;
    }
}
