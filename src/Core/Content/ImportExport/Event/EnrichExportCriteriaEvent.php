<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('system-settings')]
class EnrichExportCriteriaEvent extends Event
{
    public function __construct(
        private Criteria $criteria,
        private ImportExportLogEntity $logEntity
    ) {
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
