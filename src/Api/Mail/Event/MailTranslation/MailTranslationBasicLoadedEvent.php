<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Event\MailTranslation;

use Shopware\Api\Mail\Collection\MailTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class MailTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MailTranslationBasicCollection
     */
    protected $mailTranslations;

    public function __construct(MailTranslationBasicCollection $mailTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->mailTranslations = $mailTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getMailTranslations(): MailTranslationBasicCollection
    {
        return $this->mailTranslations;
    }
}
