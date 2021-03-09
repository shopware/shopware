<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

abstract class AbstractSeoResolver
{
    abstract public function getDecorated(): AbstractSeoResolver;

    abstract public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array;
}
