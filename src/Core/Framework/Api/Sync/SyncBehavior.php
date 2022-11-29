<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

class SyncBehavior
{
    protected ?string $indexingBehavior;

    /**
     * @var list<string>
     */
    protected array $skipIndexers = [];

    /**
     * @param list<string> $skipIndexers
     */
    public function __construct(?string $indexingBehavior = null, array $skipIndexers = [])
    {
        $this->indexingBehavior = $indexingBehavior;
        $this->skipIndexers = $skipIndexers;
    }

    public function getIndexingBehavior(): ?string
    {
        return $this->indexingBehavior;
    }

    /**
     * @return list<string>
     */
    public function getSkipIndexers(): array
    {
        return $this->skipIndexers;
    }
}
