<?php declare(strict_types=1);

namespace Shopware\System\Mail\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\System\Mail\Collection\MailBasicCollection;

class MailSearchResult extends MailBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
