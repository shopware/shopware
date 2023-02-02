<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Contracts\EventDispatcher\Event;

class DemodataRequestCreatedEvent extends Event
{
    private DemodataRequest $request;

    private Context $context;

    private ?InputInterface $input;

    /**
     * @deprecated tag:v6.5.0 - parameter $input will be required
     */
    public function __construct(DemodataRequest $request, Context $context, ?InputInterface $input = null)
    {
        if ($input === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                sprintf('Constructor of `%s` requires InputInterface parameter', __CLASS__)
            );
        }

        $this->request = $request;
        $this->context = $context;
        $this->input = $input;
    }

    public function getRequest(): DemodataRequest
    {
        return $this->request;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - return type will no longer be null
     */
    public function getInput(): ?InputInterface
    {
        return $this->input;
    }
}
