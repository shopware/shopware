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
 * Services marked with the Handler annotation are designed to be extended by adding a (tagged service)[https://symfony.com/doc/current/service_container/tags.html#reference-tagged-services] implementing a specific interface.
 * The specific service tag and interface your custom handler has to implement are defined in the annotation.
 *
 * The Handler pattern consists of two different types of objects.
 * First there are the handler, that implement the specific HandlerInterface and are tagged with the specific service tag.
 * Then there the Composite, the class that knows about all the Handlers and coordinates them.
 * The composite uses some logic that is used to determine to which handler it should delegate the work, so that just one of the handlers will be called.
 *
 * The composite is usually the class tagged with this annotation, the handlers can quickly be found by checking all the implementations of the HandlerInterface.
 */
#[Package('core')]
class Handler
{
    public function __construct(array $info)
    {
        if (!\array_key_exists('serviceTag', $info) || !\array_key_exists('handlerInterface', $info)) {
            throw new \Exception('Handler annotation must be created with a hint on the "serviceTag" and the "handlerInterface".');
        }
    }
}
