<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
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
     * Used in {@see MacroOverrideNode::compile()}
     */
    public static mixed $macroResult = null;

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
                    /** @phpstan-ignore method.dynamicName */
                    return $object->$item(...$arguments);
                }

                // Structs best only have getter with get/is prefixes, or public properties. Checking for other prefixes as well is too costly
                $getterMethods = [
                    'get' . $item,
                    'is' . $item,
                    (string) $item, // property()
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
}
