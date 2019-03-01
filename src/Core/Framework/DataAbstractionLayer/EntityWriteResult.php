<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * Contains the result of the entity write process
 */
class EntityWriteResult
{
    /**
     * @var array|string
     */
    protected $primaryKey;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var EntityExistence|null
     */
    protected $existence;

    /**
     * @param array|string $primaryKey
     */
    public function __construct($primaryKey, array $payload, ?EntityExistence $existence)
    {
        $this->primaryKey = $primaryKey;
        $this->payload = $payload;
        $this->existence = $existence;
    }

    /**
     * @return array|string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getExistence(): ?EntityExistence
    {
        return $this->existence;
    }
}
