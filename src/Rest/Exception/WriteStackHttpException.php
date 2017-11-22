<?php declare(strict_types=1);

namespace Shopware\Rest\Exception;

use Shopware\Api\Entity\Write\FieldException\WriteStackException;
use Shopware\Framework\ShopwareException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WriteStackHttpException extends HttpException implements ShopwareException
{
    /**
     * @var WriteStackException
     */
    private $exceptionStack;

    public function __construct(WriteStackException $exceptionStack, \Exception $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(400, $exceptionStack->getMessage(), $previous, $headers, $code);

        $this->exceptionStack = $exceptionStack;
    }

    public function getExceptionStack()
    {
        return $this->exceptionStack;
    }
}
