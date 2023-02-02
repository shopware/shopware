<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Symfony\Contracts\EventDispatcher\Event;

class MediaFileExtensionWhitelistEvent extends Event
{
    /**
     * @var array
     */
    private $whitelist;

    public function __construct(array $whitelist)
    {
        $this->whitelist = $whitelist;
    }

    public function getWhitelist()
    {
        return $this->whitelist;
    }

    public function setWhitelist(array $whitelist): void
    {
        $this->whitelist = $whitelist;
    }
}
