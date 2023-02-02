<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ProgressAdvancedEvent extends Event
{
    public const NAME = self::class;

    /**
     * @var int
     */
    private $step;

    public function __construct(int $step = 1)
    {
        $this->step = $step;
    }

    public function getStep(): int
    {
        return $this->step;
    }
}
