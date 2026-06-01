<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Twig;

use Shopware\Core\Framework\Log\Package;

/**
 * Stateful counter passed to Twig templates to drive line-item pagination.
 *
 * Used by `documents/includes/loop.html.twig` to decide page breaks via
 * `counter is divisible by itemsPerPage`. Stays a stateful object so the count survives nested
 * template includes within one render.
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('after-sales')]
class PaginationCounter
{
    private int $counter = 0;

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function increment(): void
    {
        ++$this->counter;
    }
}
