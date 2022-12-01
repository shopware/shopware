<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

/**
 * @package sales-channel
 *
 * @phpstan-import-type ResolvedSeoUrl from AbstractSeoResolver
 */
class EmptyPathInfoResolver extends AbstractSeoResolver
{
    private AbstractSeoResolver $decorated;

    /**
     * @internal
     */
    public function __construct(AbstractSeoResolver $decorated)
    {
        $this->decorated = $decorated;
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
