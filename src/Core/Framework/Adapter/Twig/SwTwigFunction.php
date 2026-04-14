<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;
use Twig\Template;

/**
 * @internal
 * The here defined methods are set into the compiled Twig templates in the Twig Environment override in {@see TwigEnvironment::compile()}
 */
#[Package('framework')]
class SwTwigFunction
{
    /**
     * Used in {@see MacroOverrideNode::compile()}
     */
    public static mixed $macroResult = null;

    /**
     * Cache for escaped strings to avoid repeated escaping of the same content.
     * Reset between requests via {@see SwTwigFunctionResetter} for long runner compatibility.
     *
     * @var array<string, array<string, string|Markup>>
     */
    private static array $escapeCache = [];

    /**
     * Wrapper around {@see CoreExtension::getAttribute()}
     * Implements a shortcut for receiving property values from the Shopware specific `Struct` class.
     *
     * @param list<mixed> $arguments
     */
    public static function getAttribute(
        Environment $env,
        Source $source,
        mixed $object,
        mixed $item,
        array $arguments = [],
        string $type = Template::ANY_CALL,
        bool $isDefinedTest = false,
        bool $ignoreStrictCheck = false,
        bool $sandboxed = false,
        int $lineno = -1
    ): mixed {
        try {
            if ($object instanceof Struct) {
                FieldVisibility::$isInTwigRenderingContext = true;
                if ($type === Template::METHOD_CALL) {
                    /** @phpstan-ignore method.dynamicName */
                    return $object->$item(...$arguments);
                }

                /** @see DefinitionValidator::validateStruct() */
                $getterMethods = [
                    'get' . $item,
                    'is' . $item,
                    'has' . $item,
                    'was' . $item,
                    (string) $item, // property()
                    'has' . preg_replace('/^has/', '', $item),
                    'has' . preg_replace('/^was/', '', $item),
                ];
                foreach ($getterMethods as $getterMethod) {
                    if (method_exists($object, $getterMethod)) {
                        /** @phpstan-ignore method.dynamicName */
                        return $object->$getterMethod();
                    }
                }
            }

            return CoreExtension::getAttribute($env, $source, $object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck, $sandboxed, $lineno);
        } catch (\Throwable) {
            return CoreExtension::getAttribute($env, $source, $object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck, $sandboxed, $lineno);
        } finally {
            FieldVisibility::$isInTwigRenderingContext = false;
        }
    }

    /**
     * Wrapper around {@see EscaperRuntime::escape}
     * Caches the escaped value to increase the performance
     */
    public static function escapeFilter(
        EscaperRuntime $escaperRuntime,
        mixed $string,
        string $strategy = 'html',
        ?string $charset = null,
        bool $autoescape = false,
    ): string|Markup {
        if ($string === null) {
            $string = '';
        }

        if (\is_scalar($string)) {
            $string = (string) $string;
        }

        $isString = \is_string($string);

        if ($isString) {
            if (isset(self::$escapeCache[$string][$strategy])) {
                return self::$escapeCache[$string][$strategy];
            }

            if (Uuid::isValid($string)) {
                self::$escapeCache[$string][$strategy] = $string;

                return $string;
            }
        }

        try {
            $result = $escaperRuntime->escape($string, $strategy, $charset, $autoescape);
        } catch (RuntimeError) {
            return $string;
        }

        if (!$isString) {
            return $result;
        }

        self::$escapeCache[$string][$strategy] = $result;

        return $result;
    }

    /**
     * Resets the escape filter cache.
     *
     * This method is called by {@see SwTwigFunctionResetter} between requests
     * in long runner environments (RoadRunner, FrankenPHP, Swoole) to prevent
     * memory leaks from unbounded cache growth.
     */
    public static function resetEscapeCache(): void
    {
        self::$escapeCache = [];
    }
}
