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
     * EntityWriteResult constructor
     *
     * @param array|string         $primaryKey
     * @param array                $payload
     * @param EntityExistence|null $existence
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

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return EntityExistence|null
     */
    public function getExistence(): ?EntityExistence
    {
        return $this->existence;
    }
}
