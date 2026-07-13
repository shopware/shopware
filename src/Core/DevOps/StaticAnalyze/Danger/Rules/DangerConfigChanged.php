<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Danger always executes the `.danger.php` of the target branch, so changes to it in a pull
 * request are not applied to that same pull request's Danger run.
 *
 * @internal
 */
#[Package('framework')]
class DangerConfigChanged
{
    public function __invoke(Context $context): void
    {
        if ($context->platform->pullRequest->getFiles()->has('.danger.php')) {
            $context->notice('Any changes to .danger.php will not be reflected in your pull request. Commit your changes separately.');
        }
    }
}
