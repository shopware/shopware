<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Exception\TwigMethodCallNotAllowedException;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;
use Twig\Template;

#[Package('framework')]
/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal
 */
class SwTwigFunction
{
    /**
     * Methods that change a request context or run a callback with changed state, next to set/add/remove/lock methods.
     */
    private const DENIED_CONTEXT_METHODS = ['scope', 'state', 'live', 'withPermissions', 'enableInheritance', 'disableInheritance', 'assign', 'assignRecursive'];

    /**
     * Request methods that rebuild the request or change process-wide state, next to set methods.
     */
    private const DENIED_REQUEST_METHODS = ['initialize', 'createFromGlobals', 'create', 'duplicate', 'overrideGlobals', 'enableHttpMethodParameterOverride'];

    /**
     * @deprecated tag:v6.7.0 - Will be removed
     */
    public static mixed $macroResult = null;

    /**
     * Denied methods by class, keyed by lower-case name as PHP resolves them; empty for unrestricted classes.
     *
     * @var array<class-string, array<string, string>>
     */
    private static array $deniedMethodsByClass = [];

    /**
     * @var array<int, array{returned: bool, value: mixed}>
     */
    private static array $macroReturnStack = [];

    public static function returnFromMacro(mixed $value): void
    {
        $stackIndex = array_key_last(self::$macroReturnStack);
        if ($stackIndex === null) {
            return;
        }

        self::$macroReturnStack[$stackIndex] = ['returned' => true, 'value' => $value];
    }

    /**
     * Returns the attribute value for a given array/object.
     *
     * @param mixed $object The object or array from where to get the item
     * @param mixed $item The item to get from the array or object
     * @param array<int|mixed> $arguments An array of arguments to pass if the item is an object method
     * @param string $type The type of attribute (@see \Twig\Template constants)
     * @param bool $isDefinedTest Whether this is only a defined check
     * @param bool $ignoreStrictCheck Whether to ignore the strict attribute check or not
     * @param int $lineno The template line where the attribute was called
     *
     * @throws RuntimeError if the attribute does not exist and Twig is running in strict mode and $isDefinedTest is false
     *
     * @return mixed The attribute value, or a Boolean when $isDefinedTest is true, or null when the attribute is not set and $ignoreStrictCheck is true
     *
     * @internal
     */
    public static function getAttribute(Environment $env, Source $source, mixed $object, mixed $item, array $arguments = [], $type = /* Template::ANY_CALL */ 'any', $isDefinedTest = false, $ignoreStrictCheck = false, bool $sandboxed = false, int $lineno = -1)
    {
        try {
            if ($object instanceof Struct) {
                FieldVisibility::$isInTwigRenderingContext = true;

                if ($type === Template::METHOD_CALL) {
                    if ((self::$deniedMethodsByClass[$object::class] ??= self::deniedMethods($object)) !== []) {
                        self::assertCallAllowed($object, (string) $item, $type);
                    }

                    // @phpstan-ignore-next-line
                    return $object->$item(...$arguments);
                }

                $getter = 'get' . (string) $item;
                $isGetter = 'is' . (string) $item;

                if (method_exists($object, $getter)) { // @phpstan-ignore-next-line
                    return $object->$getter();
                }

                if (method_exists($object, $isGetter)) { // @phpstan-ignore-next-line
                    return $object->$isGetter();
                }

                if (method_exists($object, $item)) {
                    if ((self::$deniedMethodsByClass[$object::class] ??= self::deniedMethods($object)) !== []) {
                        self::assertCallAllowed($object, (string) $item, $type);
                    }

                    // @phpstan-ignore-next-line
                    return $object->$item();    // property()
                }
            } elseif (\is_object($object) && (self::$deniedMethodsByClass[$object::class] ??= self::deniedMethods($object)) !== []) {
                self::assertCallAllowed($object, (string) $item, $type);
            }

            return CoreExtension::getAttribute($env, $source, $object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck, $sandboxed, $lineno);
        } catch (TwigMethodCallNotAllowedException $exception) {
            // The fallback below would call the denied method.
            throw $exception;
        } catch (\Throwable) {
            return CoreExtension::getAttribute($env, $source, $object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck, $sandboxed, $lineno);
        } finally {
            FieldVisibility::$isInTwigRenderingContext = false;
        }
    }

    /**
     * Escapes a string.
     *
     * @param mixed $string The value to be escaped
     * @param string $strategy The escaping strategy
     * @param ?string $charset The charset
     * @param bool $autoescape Whether the function is called by the auto-escaping feature (true) or by the developer (false)
     *
     * @return string|Markup
     */
    public static function escapeFilter(Environment $env, mixed $string, string $strategy = 'html', $charset = null, $autoescape = false)
    {
        if ($string === null) {
            $string = '';
        }

        if (\is_int($string)) {
            $string = (string) $string;
        }
        static $strings = [];

        $isString = \is_string($string);

        if ($isString && isset($strings[$string][$strategy])) {
            return $strings[$string][$strategy];
        }

        $result = $env->getRuntime(EscaperRuntime::class)->escape($string, $strategy, $charset, $autoescape);

        if (!$isString) {
            return $result;
        }

        $strings[$string][$strategy] = $result;

        return $result;
    }

    /**
     * @param \Closure(): mixed $macro
     */
    public static function callMacro(\Closure $macro): mixed
    {
        $stackIndex = \count(self::$macroReturnStack);
        self::$macroReturnStack[$stackIndex] = ['returned' => false, 'value' => null];

        try {
            $result = $macro();
            $return = self::$macroReturnStack[$stackIndex];
        } finally {
            unset(self::$macroReturnStack[$stackIndex]);
        }

        return $return['returned'] ? $return['value'] : $result;
    }

    private static function assertCallAllowed(object $object, string $item, string $type): void
    {
        $method = self::$deniedMethodsByClass[$object::class][strtolower($item)] ?? null;
        if ($method === null) {
            return;
        }

        if ($type !== Template::METHOD_CALL) {
            // Mirrors the lookup order: Structs prefer get/is accessors, other objects public properties.
            if ($object instanceof Struct) {
                if (method_exists($object, 'get' . $item) || method_exists($object, 'is' . $item)) {
                    return;
                }
            } elseif (\array_key_exists($item, get_object_vars($object))) {
                return;
            }
        }

        throw AdapterException::twigMethodCallNotAllowed($object::class, $method);
    }

    /**
     * @return array<string, string>
     */
    private static function deniedMethods(object $object): array
    {
        if ($object instanceof Context || $object instanceof SalesChannelContext || $object instanceof ContextSource) {
            $pattern = '/^(set|add|remove|lock)[A-Z]/';
            $named = self::DENIED_CONTEXT_METHODS;
        } elseif ($object instanceof Request) {
            $pattern = '/^set[A-Z]/';
            $named = self::DENIED_REQUEST_METHODS;
        } else {
            return [];
        }

        $deniedMethods = [];
        foreach (get_class_methods($object) as $method) {
            if (preg_match($pattern, $method) === 1 || \in_array($method, $named, true)) {
                $deniedMethods[strtolower($method)] = $method;
            }
        }

        return $deniedMethods;
    }
}
