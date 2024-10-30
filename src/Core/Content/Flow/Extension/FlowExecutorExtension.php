<?php

namespace Shopware\Core\Content\Flow\Extension;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Framework\Extensions\Extension;

/**
 * This extension allows you to control the flow of execution or to pre-load and post-load specific data, enabling added monitoring capabilities or the ability to trigger external services.
 * @extends Extension<void>
 */
class FlowExecutorExtension extends Extension
{
    public const NAME = 'flow.executor';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        public readonly Flow $flow,
        public readonly StorableFlow $event
    ) {}
}
