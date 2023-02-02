<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SeoUrlPlaceholderHandlerInterface
{
    /**
     * @param string $name
     */
    public function generate($name, array $parameters = []): string;

    public function replace(string $content, string $host, SalesChannelContext $context): string;
}
