<?php declare(strict_types=1);

namespace Shopware\ProductVote\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductVoteWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'product_vote.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_vote';
    }
}
