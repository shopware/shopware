<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

class DataMappingEvent extends Event implements ShopwareEvent
{
    /**
     * @var DataBag
     */
    private $input;

    /**
     * @var array
     */
    private $output;

    /**
     * @var Context
     */
    private $context;

    public function __construct(DataBag $input, array $output, Context $context)
    {
        $this->input = $input;
        $this->output = $output;
        $this->context = $context;
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
