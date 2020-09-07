<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Symfony\Contracts\EventDispatcher\Event;

class DemodataRequestCreatedEvent extends Event
{
    /** @var DemodataRequest */
    private $request;

    /** @var Context */
    private $context;

    public function __construct(DemodataRequest $request, Context $context)
    {
        $this->request = $request;
        $this->context = $context;
    }

    public function getRequest(): DemodataRequest
    {
        return $this->request;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
