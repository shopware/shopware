<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

class ProductNotFoundException extends \Exception
{
    public const CODE = 300000;

    public function __construct(string $productId)
    {
        parent::__construct(sprintf('Product for id %s not found', $productId), self::CODE);
    }
}
