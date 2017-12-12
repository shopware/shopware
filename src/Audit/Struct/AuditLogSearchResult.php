<?php declare(strict_types=1);

namespace Shopware\Audit\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Audit\Collection\AuditLogBasicCollection;

class AuditLogSearchResult extends AuditLogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
