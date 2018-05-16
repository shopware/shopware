<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MailTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.search.result.loaded';

    /**
     * @var \Shopware\System\Mail\Aggregate\MailTranslation\Struct\MailTranslationSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
