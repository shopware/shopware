<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('business-ops')]
class Flow extends Struct
{
    /**
     * @param list<Sequence> $sequences
     * @param array<string, Sequence> $flat
     */
    public function __construct(
        protected string $id,
        protected array $sequences = [],
        protected array $flat = []
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return list<Sequence>
     */
    public function getSequences(): array
    {
        return $this->sequences;
    }

    /**
     * @return array<string, Sequence>
     */
    public function getFlat(): array
    {
        return $this->flat;
    }

    public function jump(string $id): void
    {
        $this->sequences = array_filter([$this->flat[$id] ?? null]);
    }
}
