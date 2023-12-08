<?php
declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\ThemeCollection;

#[Package('storefront')]
class DatabaseAvailableThemeProvider extends AbstractAvailableThemeProvider
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $salesChannelRepository)
    {
    }

    public function getDecorated(): AbstractAvailableThemeProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Context $context, bool $activeOnly): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        if ($activeOnly) {
            $criteria->addFilter(new EqualsFilter('active', 1));
        }

        $criteria->addAssociation('themes');

        /** @var SalesChannelCollection $result */
        $result = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        $list = [];

        foreach ($result->getElements() as $salesChannel) {
            /** @var ThemeCollection|null $themes */
            $themes = $salesChannel->getExtensionOfType('themes', ThemeCollection::class);
            if (!$themes || !$theme = $themes->first()) {
                continue;
            }

            $list[$salesChannel->getId()] = $theme->getId();
        }

        return $list;
    }
}
