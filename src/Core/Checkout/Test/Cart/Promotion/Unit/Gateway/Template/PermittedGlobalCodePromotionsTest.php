<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Gateway\Template;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Gateway\Template\ActiveDateRange;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedGlobalCodePromotions;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PermittedGlobalCodePromotionsTest extends TestCase
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannel;

    public function setUp(): void
    {
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setId('DE');
    }

    /**
     * This test verifies, that we get the
     * expected and defined criteria from the template.
     *
     * @test
     * @group promotions
     */
    public function testCriteria()
    {
        $codes = ['code-123'];

        $template = new PermittedGlobalCodePromotions($codes, $this->salesChannel->getId());

        static::assertEquals($this->getExpectedFilter($codes)->getQueries(), $template->getQueries());
    }

    /**
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getExpectedFilter(array $codes): MultiFilter
    {
        return new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', $this->salesChannel->getId()),
                // yes, i know, this is not the best isolation, but its actually what we want
                new ActiveDateRange(),
                new EqualsFilter('useCodes', true),
                new EqualsFilter('useIndividualCodes', false),
                new EqualsAnyFilter('code', $codes),
            ]
        );
    }
}
