<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface ConfirmUrlAware extends FlowEventAware
{
    public const CONFIRM_URL = 'confirmUrl';

    public function getConfirmUrl(): string;
}
