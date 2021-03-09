<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

class EmptyPathInfoResolver extends AbstractSeoResolver
{
    /**
     * @var AbstractSeoResolver
     */
    private $decorated;

    public function __construct(AbstractSeoResolver $decorated)
    {
        $this->decorated = $decorated;
    }

    public function getDecorated(): AbstractSeoResolver
    {
        return $this->decorated;
    }

    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        $seoPathInfo = ltrim($pathInfo, '/');
        if ($seoPathInfo === '') {
            return ['pathInfo' => '/', 'isCanonical' => false];
        }

        return $this->getDecorated()->resolve($languageId, $salesChannelId, $pathInfo);
    }
}
