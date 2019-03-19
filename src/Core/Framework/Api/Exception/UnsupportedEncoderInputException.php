<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UnsupportedEncoderInputException extends ShopwareHttpException
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct('Unsupported encoder data provided. Only entities and entity collections are supported');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__UNSUPPORTED_ENCODER_INPUT';
    }
}
