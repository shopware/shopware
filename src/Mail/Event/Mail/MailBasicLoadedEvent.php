<?php declare(strict_types=1);

namespace Shopware\Mail\Event\Mail;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Mail\Collection\MailBasicCollection;

class MailBasicLoadedEvent extends NestedEvent
{
    const NAME = 'mail.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MailBasicCollection
     */
    protected $mails;

    public function __construct(MailBasicCollection $mails, TranslationContext $context)
    {
        $this->context = $context;
        $this->mails = $mails;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getMails(): MailBasicCollection
    {
        return $this->mails;
    }
}
