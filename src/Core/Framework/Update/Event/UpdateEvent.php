<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Event;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package system-settings
 */
abstract class UpdateEvent extends Event
{
    public function __construct(private readonly Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
