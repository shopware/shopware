<?php declare(strict_types=1);

namespace Shopware\System\Mail\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Mail\Collection\MailAttachmentBasicCollection;

class MailAttachmentSearchResult extends MailAttachmentBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
