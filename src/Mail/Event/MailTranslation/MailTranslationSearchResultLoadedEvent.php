<?php declare(strict_types=1);

namespace Shopware\Mail\Event\MailTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Mail\Struct\MailTranslationSearchResult;

class MailTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'mail_translation.search.result.loaded';

    /**
     * @var MailTranslationSearchResult
     */
    protected $result;

    public function __construct(MailTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
