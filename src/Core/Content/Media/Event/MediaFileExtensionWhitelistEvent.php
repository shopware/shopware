<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package content
 */
class MediaFileExtensionWhitelistEvent extends Event
{
    public function __construct(private array $whitelist)
    {
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
