<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Language\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\SalesChannel\SalesChannelLanguageDefinition;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(SalesChannelLanguageDefinition::class)]
class SalesChannelLanguageDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelLanguageDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('language.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
