<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('fundamentals@after-sales')]
class EnrichExportCriteriaEvent extends Event implements ShopwareEvent
{
    public function __construct(
        private Criteria $criteria,
        private ImportExportLogEntity $logEntity,
        private readonly ?Context $context = null
    ) {
        if ($context === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $context to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }
    }

    public function getContext(): Context
    {
        // tag:v6.8.0 - Remove this null check, $context will be required
        if ($this->context === null) {
            throw ImportExportException::invalidEventData('No context provided. Pass $context to the constructor of ' . static::class);
        }

        return $this->context;
    }

    /**
     * @deprecated tag:v6.8.0 - Use getContext() instead, $context will be required in the constructor.
     */
    public function getNullableContext(): ?Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'getNullableContext() is deprecated, use getContext() instead.');

        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function setCriteria(Criteria $criteria): void
    {
        $this->criteria = $criteria;
    }

    public function getLogEntity(): ImportExportLogEntity
    {
        return $this->logEntity;
    }

    public function setLogEntity(ImportExportLogEntity $logEntity): void
    {
        $this->logEntity = $logEntity;
    }
}
