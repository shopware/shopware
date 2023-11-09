<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - will be removed
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
        Feature::triggerDeprecationOrThrow(
            'ES_MULTILINGUAL_INDEX',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0')
        );

        return $this->languageId;
    }
}
