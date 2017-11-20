<?php declare(strict_types=1);

namespace Shopware\Mail\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Mail\Collection\MailBasicCollection;

class MailSearchResult extends MailBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
