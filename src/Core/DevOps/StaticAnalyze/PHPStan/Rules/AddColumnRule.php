<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration\AddColumnRule as NewAddColumnRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - reason:remove-phpstan-rule - Will be removed. Use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration\AddColumnRule instead
 */
#[Package('core')]
class AddColumnRule extends NewAddColumnRule
{
}
