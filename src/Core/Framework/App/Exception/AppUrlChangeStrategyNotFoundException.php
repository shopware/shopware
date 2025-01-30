<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppUrlChangeStrategyNotFoundException extends \RuntimeException
{
    public function __construct(string $strategyName)
    {
        parent::__construct('Unable to find AppUrlChangeResolver with name: "' . $strategyName . '".');
    }
}
