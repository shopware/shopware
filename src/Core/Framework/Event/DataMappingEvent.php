<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\EventDispatcher\Event;

class DataMappingEvent extends Event implements ShopwareEvent
{
    /**
     * @var DataBag
     */
    protected $input;

    /**
     * @var array
     */
    protected $output;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    private $eventName;

    public function __construct(string $eventName, DataBag $input, array $output, Context $context)
    {
        $this->eventName = $eventName;
        $this->input = $input;
        $this->output = $output;
        $this->context = $context;
    }

    public function getName(): string
    {
        return $this->eventName;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getInput(): DataBag
    {
        return $this->input;
    }

    public function getOutput(): array
    {
        return $this->output;
    }

    public function setOutput(array $output): void
    {
        $this->output = $output;
    }
}
