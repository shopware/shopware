<?php declare(strict_types=1);

namespace Shopware\Api\Audit\Struct;

use Shopware\Api\Audit\Collection\AuditLogBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class AuditLogSearchResult extends AuditLogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
