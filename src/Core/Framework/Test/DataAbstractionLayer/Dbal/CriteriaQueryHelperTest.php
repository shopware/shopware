<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class CriteriaQueryHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInvalidSortingDirection(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $criteria = new Criteria();

        $criteria->addSorting(new FieldSorting('rate', 'invalid direction'));

        static::expectException(InvalidSortingDirectionException::class);
        $taxRepository->search($criteria, $context);
    }

    public function testFkFieldSerialization(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepositoryInterface $themeRepository */
        $themeRepository = $this->getContainer()->get('theme.repository');
        /** @var EntityRepositoryInterface $themeSalesChannelRepository */
        $themeSalesChannelRepository = $this->getContainer()->get('theme_sales_channel.repository');

        $themeId = Uuid::randomHex();
        $salesChannelId = Defaults::SALES_CHANNEL;

        $themeRepository->create([[
            'id' => $themeId,
            'name' => __METHOD__,
            'author' => __METHOD__,
            'active' => false,
            'salesChannels' => [[
                'id' => $salesChannelId,
            ]],
        ]], $context);

        $criteria = new Criteria([$salesChannelId]);
        $primaryKey = $themeSalesChannelRepository->searchIds($criteria, $context)->firstId();

        static::assertSame($salesChannelId, $primaryKey);

        $themeSalesChannelRepository->delete([['salesChannelId' => $salesChannelId]], $context);
        $themeRepository->delete([['id' => $themeId]], $context);
    }
}
