<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Temporary buffered flow that can be converted into
 * a {@see StorableFlow} by the {@see FlowFactory}.
 *
 * @internal
 *
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
readonly class BufferedFlow
{
    /**
     * @param array<string, mixed> $stored
     */
    public function __construct(
        public string $eventName,
        public Context $eventContext,
        public array $stored,
    ) {
    }
}
