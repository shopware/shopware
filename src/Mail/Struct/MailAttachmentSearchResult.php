<?php declare(strict_types=1);

namespace Shopware\Mail\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Mail\Collection\MailAttachmentBasicCollection;

class MailAttachmentSearchResult extends MailAttachmentBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
