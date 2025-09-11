<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ResolvedSeoUrl from AbstractSeoResolver
 */
#[Package('inventory')]
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
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $queryString will be added
     *
     * @return ResolvedSeoUrl
     */
    public function resolve(string $languageId, string $salesChannelId, string $pathInfo/* , ?string $queryString = null */): array
    {
        $queryString = \func_num_args() === 4 ? func_get_arg(3) : null;

        $seoPathInfo = ltrim($pathInfo, '/');
        if ($seoPathInfo === '') {
            return ['pathInfo' => '/', 'isCanonical' => false];
        }

        /** @phpstan-ignore-next-line parameter $queryString will be added in v6.8 */
        return $this->getDecorated()->resolve($languageId, $salesChannelId, $pathInfo, $queryString);
    }
}
