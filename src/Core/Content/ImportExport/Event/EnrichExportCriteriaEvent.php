<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\Event;

class EnrichExportCriteriaEvent extends Event
{
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var ImportExportLogEntity
     */
    private $logEntity;

    public function __construct(Criteria $criteria, ImportExportLogEntity $logEntity)
    {
        $this->criteria = $criteria;
        $this->logEntity = $logEntity;
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
