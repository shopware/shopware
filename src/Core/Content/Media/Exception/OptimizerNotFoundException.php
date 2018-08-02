<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class OptimizerNotFoundException extends ShopwareHttpException
{
    public function __construct(string $mimeType)
    {
        parent::__construct(sprintf('Optimizer for MIME-type "%s" not found.', $mimeType));
    }
}
