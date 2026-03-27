<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class MediaFileExtensionWhitelistEvent extends Event
{
    /**
     * @param list<string> $whitelist
     */
    public function __construct(private array $whitelist)
    {
    }

    /**
     * @return list<string>
     */
    public function getWhitelist()
    {
        return $this->whitelist;
    }

    /**
     * @param list<string> $whitelist
     */
    public function setWhitelist(array $whitelist): void
    {
        $this->whitelist = $whitelist;
    }
}
