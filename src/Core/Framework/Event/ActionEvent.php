<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;

abstract class ActionEvent extends Event implements ActionEventInterface
{
    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    abstract public function getName(): string;

    public function getContext(): Context
    {
        return $this->context;
    }
}
