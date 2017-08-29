<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldException;

use Shopware\Framework\ShopwareException;

class ApiStackException extends \DomainException implements ShopwareException
{

    /**
     * @var ApiFieldException[]
     */
    private $exceptions;

    public function __construct(ApiFieldException ...$exceptions)
    {
        $this->exceptions = $exceptions;
        parent::__construct(sprintf('Mapping failed, got %s failure(s). %s', count($exceptions), print_r($this->toArray(), true)));
    }

    /**
     * @return ApiFieldException[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    public function toArray(): array
    {
        $result = [];

        foreach($this->exceptions as $exception) {
            if(!is_array($result[$exception->getPath()])) {
                $result[$exception->getPath()] = [];
            }


            $result[$exception->getPath()][$exception->getConcern()] = [$exception->toArray(), $exception->getTraceAsString()];
        }

        return $result;
    }
}