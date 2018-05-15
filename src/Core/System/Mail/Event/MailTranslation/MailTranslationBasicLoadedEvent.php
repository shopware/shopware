<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\MailTranslation;

use Shopware\System\Mail\Collection\MailTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MailTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MailTranslationBasicCollection
     */
    protected $mailTranslations;

    public function __construct(MailTranslationBasicCollection $mailTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mailTranslations = $mailTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMailTranslations(): MailTranslationBasicCollection
    {
        return $this->mailTranslations;
    }
}
