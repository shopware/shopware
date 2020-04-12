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

    public function __construct(bool $failOnError, bool $singleOperation = false)
    {
        $this->failOnError = $failOnError;
        $this->singleOperation = $singleOperation;
    }

    public function failOnError(): bool
    {
        return $this->failOnError;
    }

    public function useSingleOperation(): bool
    {
        return $this->singleOperation;
    }
}
