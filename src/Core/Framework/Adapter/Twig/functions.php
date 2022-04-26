<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Twig\Environment;
use Twig\Source;

function sw_get_attribute(Environment $env, Source $source, $object, $item, array $arguments = [], $type = /* Template::ANY_CALL */ 'any', $isDefinedTest = false, $ignoreStrictCheck = false, $sandboxed = false, int $lineno = -1)
{
    try {
        if ($object instanceof Entity) {
            FieldVisibility::$isInTwigRenderingContext = true;

            $getter = 'get' . ucfirst($item);

            return $object->$getter();
        }

        return twig_get_attribute($env, $source, $object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck, $sandboxed, $lineno);
    } catch (\Throwable $e) {
        return twig_get_attribute($env, $source, $object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck, $sandboxed, $lineno);
    } finally {
        FieldVisibility::$isInTwigRenderingContext = false;
    }
}

function sw_escape_filter(Environment $env, $string, $strategy = 'html', $charset = null, $autoescape = false)
{
    if (\is_int($string)) {
        $string = (string) $string;
    }
    static $strings = [];
    if (\is_string($string) && isset($strings[$string][$strategy])) {
        return $strings[$string][$strategy];
    }

    $result = twig_escape_filter($env, $string, $strategy, $charset, $autoescape);

    if (!\is_string($string)) {
        return $result;
    }

    $strings[$string][$strategy] = $result;

    return $result;
}
