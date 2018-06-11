<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationSearchResult;

class MailTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
