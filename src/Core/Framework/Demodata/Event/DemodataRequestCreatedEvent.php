<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @final
 */
#[Package('core')]
class DemodataRequestCreatedEvent extends Event
{
    public function __construct(
        private readonly DemodataRequest $request,
        private readonly Context $context,
        private readonly InputInterface $input
    ) {
    }

    public function getRequest(): DemodataRequest
    {
        return $this->request;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }
}
