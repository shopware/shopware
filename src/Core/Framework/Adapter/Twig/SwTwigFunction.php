<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;
use Twig\Template;

#[Package('core')]
class SwTwigFunction
{
    public static mixed $macroResult = null;

    /**
     * Returns the attribute value for a given array/object.
     *
     * @param mixed  $object            The object or array from where to get the item
     * @param mixed  $item              The item to get from the array or object
     * @param array<mixed>  $arguments         An array of arguments to pass if the item is an object method
     * @param string $type              The type of attribute (@see \Twig\Template constants)
     * @param bool   $isDefinedTest     Whether this is only a defined check
     * @param bool   $ignoreStrictCheck Whether to ignore the strict attribute check or not
     * @param int    $lineno            The template line where the attribute was called
     * @param bool   $sandboxed         When true the extension use SandboxExtension
     *
     * @return mixed The attribute value, or a Boolean when $isDefinedTest is true, or null when the attribute is not set and $ignoreStrictCheck is true
     *
     * @internal
     */
    public static function getAttribute(Environment $env, Source $source, mixed $object, mixed $item, array $arguments = [], $type = /* Template::ANY_CALL */ 'any', $isDefinedTest = false, $ignoreStrictCheck = false, $sandboxed = false, int $lineno = -1)
    {
        try {
            if ($object instanceof Struct) {
                FieldVisibility::$isInTwigRenderingContext = true;

                if ($type === Template::METHOD_CALL) { // @phpstan-ignore-next-line
                    return $object->$item(...$arguments);
                }

                $getter = 'get' . ucfirst((string) $item);
                $isGetter = 'is' . ucfirst((string) $item);

                if (method_exists($object, $getter)) { // @phpstan-ignore-next-line
                    return $object->$getter();
                } elseif (method_exists($object, $isGetter)) { // @phpstan-ignore-next-line
                    return $object->$isGetter();
                } elseif (method_exists($object, $item)) { // @phpstan-ignore-next-line
                    return $object->$item();    // property()
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
     * Escapes a string.
     *
     * @param mixed  $string     The value to be escaped
     * @param string $strategy   The escaping strategy
     * @param ?string $charset    The charset
     * @param bool   $autoescape Whether the function is called by the auto-escaping feature (true) or by the developer (false)
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
     * @param array<array-key, mixed> $args
     * @param array<array-key, mixed> $context
     *
     * @return mixed
     */
    public static function callMacro(Template $template, string $method, array $args, int $lineno, array $context, Source $source)
    {
        $result = CoreExtension::callMacro($template, $method, $args, $lineno, $context, $source);

        if (self::$macroResult !== null) {
            $result = self::$macroResult;
            self::$macroResult = null;
        }

        return $result;
    }
}
