<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationBasicCollection;

class MailTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationBasicCollection
     */
    protected $mailTranslations;

    public function __construct(MailTranslationBasicCollection $mailTranslations, Context $context)
    {
        $this->context = $context;
        $this->mailTranslations = $mailTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailTranslations(): MailTranslationBasicCollection
    {
        return $this->mailTranslations;
    }
}
