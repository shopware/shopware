<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface ResetUrlAware extends FlowEventAware
{
    public const RESET_URL = 'resetUrl';

    public function getResetUrl(): string;
}
