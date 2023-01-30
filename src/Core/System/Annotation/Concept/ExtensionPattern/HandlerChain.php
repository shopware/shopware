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
 * The HandlerChain pattern is very similar to the `\Shopware\Core\System\Annotation\Concept\ExtensionPattern\Handler` pattern.
 * But instead of just calling one of it's handlers the Composite will call all of the handlers consecutively
 * and will forward the output of the handler to the next one.
 *
 * When using the HandlerChain pattern the order in which the handlers are called is very important,
 * therefore the (priority attribute)[https://symfony.com/doc/current/service_container/tags.html#reference-tagged-services] should be used to define the order of the handler.
 */
#[Package('core')]
class HandlerChain extends Handler
{
}
