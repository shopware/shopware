<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
#[Package('core')]
class ElasticsearchLanguageIndexIteratorMessage implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly string $languageId)
    {
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }
}
