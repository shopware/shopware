<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AssociationLoadingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AssociationLoadingProcessor
 */
class AssociationLoadingProcessorTest extends TestCase
{
    public function testPrepare(): void
    {
        $request = new Request();
        $criteria = new Criteria();
        $context = $this->createMock(SalesChannelContext::class);

        $processor = new AssociationLoadingProcessor();
        $processor->prepare($request, $criteria, $context);

        static::assertTrue($criteria->hasAssociation('manufacturer'));
        static::assertTrue($criteria->hasAssociation('options'));
    }
}
