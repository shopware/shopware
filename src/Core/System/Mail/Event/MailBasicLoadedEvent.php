<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Mail\Collection\MailBasicCollection;

class MailBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'mail.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var MailBasicCollection
     */
    protected $mails;

    public function __construct(MailBasicCollection $mails, Context $context)
    {
        $this->context = $context;
        $this->mails = $mails;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMails(): MailBasicCollection
    {
        return $this->mails;
    }
}
