<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class FileService
{
    /**
     * @param list<string> $allowedProtocols
     */
    public function __construct(private readonly array $allowedProtocols)
    {
    }

    public function isUrl(string $url): bool
    {
        return (bool) filter_var($url, \FILTER_VALIDATE_URL) && $this->isProtocolAllowed($url);
    }

    private function isProtocolAllowed(string $url): bool
    {
        $fragments = explode(':', $url);

        return \in_array(strtolower($fragments[0]), $this->allowedProtocols, true);
    }
}
