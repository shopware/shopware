<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UnsupportedEncoderInputException extends ShopwareHttpException
{
    /**
     * {@inheritdoc}
     */
    public function __construct(int $code = 0, \Throwable $previous = null)
    {
        $message = 'Unsupported encoder data provided. Only entities and entity collections are supported';

        parent::__construct($message, $code, $previous);
    }
}
