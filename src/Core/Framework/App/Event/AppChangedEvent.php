<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AppChangedEvent extends Event implements ShopwareEvent
{
    /**
     * @var AppEntity
     */
    private $app;

    /**
     * @var Context
     */
    private $context;

    public function __construct(AppEntity $app, Context $context)
    {
        $this->app = $app;
        $this->context = $context;
    }

    abstract public function getName(): string;

    public function getApp(): AppEntity
    {
        return $this->app;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
