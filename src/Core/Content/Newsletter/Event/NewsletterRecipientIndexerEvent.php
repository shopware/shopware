<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class NewsletterRecipientIndexerEvent extends NestedEvent
{
    private Context $context;

    private array $ids;

    private array $skip;

    public function __construct(array $ids, Context $context, array $skip = [])
    {
        $this->context = $context;
        $this->ids = $ids;
        $this->skip = $skip;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getSkip(): array
    {
        return $this->skip;
    }
}
