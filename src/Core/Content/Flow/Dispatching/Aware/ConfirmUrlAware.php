<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

interface ConfirmUrlAware extends FlowEventAware
{
    public const CONFIRM_URL = 'confirmUrl';

    public function getConfirmUrl(): string;
}
