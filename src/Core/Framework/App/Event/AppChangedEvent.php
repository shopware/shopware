<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AppChangedEvent extends Event implements ShopwareEvent
{
    /**
     * @var string
     */
    private $appId;

    /**
     * @var Context
     */
    private $context;

    public function __construct(string $appId, Context $context)
    {
        $this->appId = $appId;
        $this->context = $context;
    }

    abstract public function getName(): string;

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
