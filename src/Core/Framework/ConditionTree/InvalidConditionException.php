<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ConditionTree;

use Shopware\Core\Framework\ShopwareException;

class InvalidConditionException extends \RuntimeException implements ShopwareException
{
    /**
     * @var string
     */
    protected $conditionName;

    public function __construct(string $conditionName)
    {
        parent::__construct();
        $this->conditionName = $conditionName;
    }
}
