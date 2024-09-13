<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class NotExtendFlowEventAwareRule
{
    #[TestRule]
    public function doNotExtendFlowEventAware(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::isInterface())
            ->shouldNotDependOn()
            ->classes(Selector::classname(FlowEventAware::class));
    }
}
