<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - will be removed, as it was not used
 */
#[Package('core')]
class NoIndexedDocumentsException extends ShopwareHttpException
{
    final public const CODE = 'ELASTICSEARCH_NO_INDEXED_DOCUMENTS';

    public function __construct(string $entityName)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        parent::__construct(
            sprintf('No indexed documents found for entity %s', $entityName)
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return self::CODE;
    }
}
