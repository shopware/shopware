<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Exception\TwigMethodCallNotAllowedException;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Source;
use Twig\Template;

/**
 * @internal
 */
#[Package('framework')]
class SwTwigFunction
{
    /**
     * Methods that change a request context or run a callback with changed state, next to set/add/remove/lock methods.
     */
    private const DENIED_CONTEXT_METHODS = ['scope', 'state', 'live', 'withPermissions', 'enableInheritance', 'disableInheritance', 'assign', 'assignRecursive'];

    /**
     * Add methods a template may still call on a context: extensions only carry data for templates and PHP checks their type.
     */
    private const ALLOWED_CONTEXT_METHODS = ['addExtension', 'addArrayExtension', 'addExtensions'];

    /**
     * Request methods that rebuild the request or change process-wide state, next to set methods.
     */
    private const DENIED_REQUEST_METHODS = ['initialize', 'createFromGlobals', 'create', 'duplicate', 'overrideGlobals', 'enableHttpMethodParameterOverride'];

    /**
     * Cached instead of a getter name when a template reads a denied method like a property.
     */
    private const DENIED_GETTER = ':denied';

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

    /**
     * Resolved getter names by class and accessed item, empty string when the
     * struct has no getter and the default Twig attribute handling applies.
     *
     * @var array<class-string, array<string, string>>
     */
    private static array $getterCache = [];

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

    public static function returnFromMacro(mixed $value): void
    {
        $stackIndex = array_key_last(self::$macroReturnStack);
        if ($stackIndex === null) {
            return;
        }

        self::$macroReturnStack[$stackIndex] = ['returned' => true, 'value' => $value];
    }

    /**
     * Wrapper around {@see CoreExtension::getAttribute()}
     * Implements a shortcut for receiving property values from the Shopware specific `Struct` class.
     * The method is set into the compiled Twig templates in the Twig Environment override in {@see TwigEnvironment::compile()}.
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
                    if ((self::$deniedMethodsByClass[$object::class] ??= self::deniedMethods($object)) !== []) {
                        self::assertCallAllowed($object, (string) $item, $type);
                    }

                    /** @phpstan-ignore method.dynamicName */
                    return $object->$item(...$arguments);
                }

                $item = (string) $item;

                $getterMethod = self::$getterCache[$object::class][$item] ??= self::resolveGetter($object, $item);

                if ($getterMethod === self::DENIED_GETTER) {
                    self::assertCallAllowed($object, $item, $type);
                }

                if ($getterMethod !== '') {
                    /** @phpstan-ignore method.dynamicName */
                    return $object->$getterMethod();
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
        $allowed = [];
        if ($object instanceof Context || $object instanceof SalesChannelContext || $object instanceof ContextSource) {
            $pattern = '/^(set|add|remove|lock)[A-Z]/';
            $named = self::DENIED_CONTEXT_METHODS;
            $allowed = self::ALLOWED_CONTEXT_METHODS;
        } elseif ($object instanceof Request) {
            $pattern = '/^set[A-Z]/';
            $named = self::DENIED_REQUEST_METHODS;
        } else {
            return [];
        }

        $deniedMethods = [];
        foreach (get_class_methods($object) as $method) {
            if (\in_array($method, $allowed, true)) {
                continue;
            }

            if (preg_match($pattern, $method) === 1 || \in_array($method, $named, true)) {
                $deniedMethods[strtolower($method)] = $method;
            }
        }

        return $deniedMethods;
    }

    private static function resolveGetter(Struct $object, string $item): string
    {
        // Structs best only have getter with get/is/has prefixes, or public properties. These are the prefixes
        // {@see CoreExtension::getAttribute()} supports as well, with the same precedence: get > is > has.
        // Probing them is only done once per class and item, the result is cached in self::$getterCache.
        $getterMethods = [
            'get' . $item,
            'is' . $item,
            $item, // property()
            'has' . $item,
        ];

        foreach ($getterMethods as $getterMethod) {
            if (method_exists($object, $getterMethod)) {
                $deniedMethods = self::$deniedMethodsByClass[$object::class] ??= self::deniedMethods($object);

                return isset($deniedMethods[strtolower($getterMethod)]) ? self::DENIED_GETTER : $getterMethod;
            }
        }

        return '';
    }
}
