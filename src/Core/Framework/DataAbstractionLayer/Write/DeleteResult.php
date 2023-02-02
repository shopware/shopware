<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Use \Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult instead
 */
class DeleteResult
{
    /**
     * @var EntityWriteResult[]
     */
    private $deleted;

    /**
     * @var EntityWriteResult[]
     */
    private $notFound;

    /**
     * @var EntityWriteResult[]
     */
    private $updated = [];

    public function __construct(array $deleted, array $notFound = [], array $updated = [])
    {
        $this->deleted = $deleted;
        $this->notFound = $notFound;
        $this->updated = $updated;
    }

    public function getDeleted(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'WriteResult')
        );

        return $this->deleted;
    }

    public function getNotFound(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'WriteResult')
        );

        return $this->notFound;
    }

    public function addUpdated(array $updated): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'WriteResult')
        );

        $this->updated = array_merge_recursive($this->updated, $updated);
    }

    public function getUpdated(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'WriteResult')
        );

        return $this->updated;
    }
}
