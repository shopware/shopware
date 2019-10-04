<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

class SyncBehavior
{
    /**
     * @var bool
     */
    protected $failOnError;

    public function __construct(bool $failOnError)
    {
        $this->failOnError = $failOnError;
    }

    public function failOnError(): bool
    {
        return $this->failOnError;
    }
}
