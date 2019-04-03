<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlGenerator;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

interface SeoUrlGeneratorInterface
{
    public function getRouteName(): string;

    public function getDefaultTemplate(): string;

    public function getSeoUrlContext(Entity $entity): array;

    public function generateSeoUrls(string $salesChannelId, array $ids, string $template, bool $skipInvalid): iterable;
}
