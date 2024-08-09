<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use ApiException::unsupportedEncoderInput instead
 */
#[Package('core')]
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
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.7.0.0', 'ApiException::unsupportedEncoderInput'),
        );

        return 'FRAMEWORK__UNSUPPORTED_ENCODER_INPUT';
    }
}
