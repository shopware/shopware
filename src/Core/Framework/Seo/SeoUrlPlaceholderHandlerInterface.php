<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Seo;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SeoUrlPlaceholderHandlerInterface
{
    public function generate($name, $parameters = []): string;

    public function replace(string $content, string $host, SalesChannelContext $context): string;
}
