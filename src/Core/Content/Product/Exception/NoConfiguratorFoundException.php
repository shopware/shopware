<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

class NoConfiguratorFoundException extends \Exception
{
    public const CODE = 300001;

    public function __construct(string $productId)
    {
        parent::__construct(sprintf('Product with id %s has no configuration', $productId), self::CODE);
    }
}
