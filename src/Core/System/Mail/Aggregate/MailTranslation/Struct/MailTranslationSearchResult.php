<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationBasicCollection;

class MailTranslationSearchResult extends MailTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
