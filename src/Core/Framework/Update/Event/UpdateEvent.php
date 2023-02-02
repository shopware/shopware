<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Event;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

abstract class UpdateEvent extends Event
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
