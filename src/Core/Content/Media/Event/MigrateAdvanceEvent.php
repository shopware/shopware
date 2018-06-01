<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Symfony\Component\EventDispatcher\Event;

class MigrateAdvanceEvent extends Event
{
    public const EVENT_NAME = 'media.migrate.advance';

    /**
     * @var string
     */
    private $filename;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
}
