<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class NoIndexedDocumentsException extends ShopwareHttpException
{
    final public const CODE = 'ELASTICSEARCH_NO_INDEXED_DOCUMENTS';

    public function __construct(string $entityName)
    {
        parent::__construct(
            sprintf('No indexed documents found for entity %s', $entityName)
        );
    }

    public function getErrorCode(): string
    {
        return self::CODE;
    }
}
