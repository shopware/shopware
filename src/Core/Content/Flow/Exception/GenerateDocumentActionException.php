<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('business-ops')]
class GenerateDocumentActionException extends ShopwareHttpException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'FLOW_BUILDER__DOCUMENT_GENERATION_ERROR';
    }
}
