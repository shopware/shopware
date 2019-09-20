<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Gateway\Template;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Gateway\Template\ActiveDateRange;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedIndividualCodePromotions;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PermittedIndividualCodePromotionsTest extends TestCase
{
    /**
     * @var SalesChannelEntity
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
     * @group promotions
     */
    public function testCriteria(): void
    {
        $codes = ['code-123'];

        $template = new PermittedIndividualCodePromotions($codes, $this->salesChannel->getId());

        static::assertEquals($this->getExpectedFilter($codes)->getQueries(), $template->getQueries());
    }

    private function getExpectedFilter(array $codes): MultiFilter
    {
        return new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', $this->salesChannel->getId()),
                new ActiveDateRange(),
                new EqualsFilter('useCodes', true),
                new EqualsFilter('useIndividualCodes', true),
                new EqualsAnyFilter('promotion.individualCodes.code', $codes),
                // a payload of null means, they have not yet been redeemed
                new EqualsFilter('promotion.individualCodes.payload', null),
            ]
        );
    }
}
