<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ResolvedSeoUrl from AbstractSeoResolver
 */
#[Package('sales-channel')]
class EmptyPathInfoResolver extends AbstractSeoResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractSeoResolver $decorated)
    {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        return $this->decorated;
    }

    /**
     * @return ResolvedSeoUrl
     */
    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        $seoPathInfo = ltrim($pathInfo, '/');
        if ($seoPathInfo === '') {
            return ['pathInfo' => '/', 'isCanonical' => false];
        }

        return $this->getDecorated()->resolve($languageId, $salesChannelId, $pathInfo);
    }
}
