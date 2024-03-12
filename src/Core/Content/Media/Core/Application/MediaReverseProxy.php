<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Application;

use Shopware\Core\Framework\Log\Package;

/**
 * Used to invalidate the cached media urls from the reverse proxy
 * If you are using fastly as cdn, you should configure shopware.cdn.fastly.enabled to true
 */
#[Package('core')]
interface MediaReverseProxy
{
    public function enabled(): bool;

    /**
     * @param array<string> $urls
     */
    public function ban(array $urls): void;
}
