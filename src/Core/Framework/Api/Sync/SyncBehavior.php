<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

class SyncBehavior
{
    /**
     * @var bool
     */
    protected $failOnError;

    protected $apiVersion;

    public function __construct(bool $failOnError, int $apiVersion)
    {
        $this->failOnError = $failOnError;
        $this->apiVersion = $apiVersion;
    }

    public function failOnError(): bool
    {
        return $this->failOnError;
    }

    public function getApiVersion(): int
    {
        return $this->apiVersion;
    }
}
