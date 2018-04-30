<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Event\Mail;

use Shopware\Api\Mail\Collection\MailBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MailBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'mail.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MailBasicCollection
     */
    protected $mails;

    public function __construct(MailBasicCollection $mails, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mails = $mails;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMails(): MailBasicCollection
    {
        return $this->mails;
    }
}
