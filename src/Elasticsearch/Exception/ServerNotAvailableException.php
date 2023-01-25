<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class ServerNotAvailableException extends ShopwareHttpException
{
    final public const CODE = 'ELASTICSEARCH_SERVER_NOT_AVAILABLE';

    public function __construct()
    {
        parent::__construct('Elasticsearch server is not available');
    }

    public function getErrorCode(): string
    {
        return self::CODE;
    }
}
