<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class HttpCacheCookieEvent
{
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public array $parts
    ) {
    }
}
