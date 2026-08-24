<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * A data loader config that round-trips an arbitrary array through jsonSerialize(), for tests that need the
 * decode()/encode() cycle to carry real values (e.g. structural config comparison) rather than the fixed empty
 * shape {@see StubLoaderConfig} returns.
 *
 * @final
 */
#[Package('framework')]
readonly class StubArrayLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
