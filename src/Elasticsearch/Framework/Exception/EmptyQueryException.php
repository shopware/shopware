<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class EmptyQueryException extends ElasticsearchException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, self::EMPTY_QUERY, 'Empty query provided');
    }
}
