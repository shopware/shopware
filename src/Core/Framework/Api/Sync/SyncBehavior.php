<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

class SyncBehavior
{
    /**
     * @var bool
     */
    protected $failOnError;

    /**
     * @var bool
     */
    protected $singleOperation;

    /**
     * @var string|null
     */
    protected $indexingBehavior;

    public function __construct(
        bool $failOnError,
        bool $singleOperation = false,
        ?string $indexingBehavior = null
    ) {
        $this->failOnError = $failOnError;
        $this->singleOperation = $singleOperation;
        $this->indexingBehavior = $indexingBehavior;
    }

    public function failOnError(): bool
    {
        return $this->failOnError;
    }

    public function useSingleOperation(): bool
    {
        return $this->singleOperation;
    }

    public function getIndexingBehavior(): ?string
    {
        return $this->indexingBehavior;
    }
}
