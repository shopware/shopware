<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlGenerator;

interface SeoUrlGeneratorInterface
{
    public function getRouteName(): string;

    public function getDefaultTemplate(): string;

    public function generateSeoUrls(string $salesChannelId, array $ids, string $template): iterable;
}
