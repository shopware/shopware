<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\Query\ApiQueryQueue;

interface ApiQueryQueueAware
{
    public function setApiQueryQueue(ApiQueryQueue $queryQueue): void;
}