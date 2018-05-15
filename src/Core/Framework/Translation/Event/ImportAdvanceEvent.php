<?php declare(strict_types=1);

namespace Shopware\Framework\Translation\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Finder\SplFileInfo;

class ImportAdvanceEvent extends Event
{
    public const EVENT_NAME = 'translation.import.advance';

    /**
     * @var SplFileInfo
     */
    private $file;

    public function __construct(SplFileInfo $file)
    {
        $this->file = $file;
    }

    public function getFile(): string
    {
        return (string) $this->file;
    }
}
