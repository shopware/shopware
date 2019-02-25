<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ActionEventInterface;
use Symfony\Component\EventDispatcher\Event;

class TestActionEvent extends Event implements ActionEventInterface
{
    public const EVENT_NAME = 'test.action_event';

    /**
     * @var string
     */
    protected $name = self::EVENT_NAME;

    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
