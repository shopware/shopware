<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class FilterRelationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'filter_relation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'filter_relation';
    }
}
