<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Mail\Collection\MailBasicCollection;

class MailSearchResult extends MailBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
