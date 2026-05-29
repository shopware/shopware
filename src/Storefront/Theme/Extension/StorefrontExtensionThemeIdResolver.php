<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Extension;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\ExtensionThemeIdResolver;
use Shopware\Storefront\Theme\ThemeCollection;

#[Package('checkout')]
final class StorefrontExtensionThemeIdResolver implements ExtensionThemeIdResolver
{
    /**
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(private readonly EntityRepository $themeRepository)
    {
    }

    public function resolveThemeIdByTechnicalName(string $technicalName, Context $context): ?string
    {
        return $this->themeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName)),
            $context
        )->firstId();
    }
}
