<?php declare(strict_types=1);

namespace Shopware\Core\System\Annotation\Concept\ExtensionPattern;

use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @Target("CLASS")
 *
 * @ExtensionPattern
 *
 * Services marked with the decoratable annotation are designed to be extended via (service decoration)[https://symfony.com/doc/current/service_container/service_decoration.html].
 * The general pattern used here is the (Decorator Pattern)[https://en.wikipedia.org/wiki/Decorator_pattern].
 *
 * To be decoratable a service has to implement an interface and every public method of the service has to be part of the interface.
 * Furthermore the service is not allowed to call any public function on itself, as these calls would not be made to all Decorators.
 * Also all callers of the service must use the Interface in any type declarations and never the concrete service, as this would break if the service is decorated.
 *
 * @see \Shopware\Development\Analyze\PHPStan\Rules\Decoratable\DecoratableImplementsInterfaceRule
 * @see \Shopware\Development\Analyze\PHPStan\Rules\Decoratable\DecoratableDoesNotAddPublicMethodRule
 * @see \Shopware\Development\Analyze\PHPStan\Rules\Decoratable\DecoratableDoesNotCallOwnPublicMethodRule
 * @see \Shopware\Development\Analyze\PHPStan\Rules\Decoratable\DecoratableNotDirectlyDependetRule
 * @see \Shopware\Development\Analyze\PHPStan\Rules\Decoratable\DecoratableNotInstantiatedRule
 *
 * For an introduction on how to use decoration in your plugin refer to the (symfony docs)[https://symfony.com/doc/current/service_container/service_decoration.html]
 */
#[Package('core')]
class Decoratable
{
}
