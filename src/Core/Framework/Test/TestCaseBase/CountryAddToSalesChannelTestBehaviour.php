<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CountryAddToSalesChannelTestBehaviour
{
    abstract protected function getContainer(): ContainerInterface;

    abstract protected function getValidCountryId(): string;

    /**
     * @param string[] $additionalCountryIds
     */
    protected function addCountriesToSalesChannel(array $additionalCountryIds = [], string $salesChannelId = Defaults::SALES_CHANNEL): void
    {
        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $countryIds = array_merge([
            ['id' => $this->getValidCountryId($salesChannelId)],
        ], array_map(static function (string $countryId) {
            return ['id' => $countryId];
        }, $additionalCountryIds));

        $salesChannelRepository->update([[
            'id' => $salesChannelId,
            'countries' => $countryIds,
        ]], Context::createDefaultContext());
    }
}
