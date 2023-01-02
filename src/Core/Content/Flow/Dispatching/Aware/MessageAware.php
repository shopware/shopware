<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Component\Mime\Email;

/**
 * @package business-ops
 */
#[Package('business-ops')]
interface MessageAware extends FlowEventAware
{
    public const MESSAGE = 'message';

    public function getMessage(): Email;
}
