<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompressedCriteriaListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CompressedCriteriaListingProcessor::class)]
class CompressedCriteriaListingProcessorTest extends TestCase
{
    private CompressedCriteriaDecoder&MockObject $decoder;

    private CompressedCriteriaListingProcessor $processor;

    protected function setUp(): void
    {
        $this->decoder = $this->createMock(CompressedCriteriaDecoder::class);
        $this->processor = new CompressedCriteriaListingProcessor($this->decoder);
    }

    public function testPreparePostRequestsAreNotModified(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $request->query->set('_criteria', 'some-hash');

        $this->decoder->expects($this->never())->method('decode');
        $this->processor->prepare($request, new Criteria(), static::createStub(SalesChannelContext::class));
    }

    public function testPrepareIgnoredMissingCriteria(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);

        $this->decoder->expects($this->never())->method('decode');

        $this->processor->prepare($request, new Criteria(), static::createStub(SalesChannelContext::class));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPrepareExtractsNonCriteriaFields(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        $request->query->set('_criteria', 'encoded-payload');

        static::assertFalse($request->query->has('manufacturer'));

        $payload = [
            'limit' => 10, // criteria fields should be ignored
            'manufacturer' => 'param-value',
            'custom-flag' => true,
        ];

        $this->decoder->expects($this->once())
            ->method('decode')
            ->with('encoded-payload')
            ->willReturn($payload);

        $this->processor->prepare($request, new Criteria(), static::createStub(SalesChannelContext::class));

        static::assertTrue($request->query->has('manufacturer'), 'Custom param "manufacturer" should be in query');
        static::assertSame('param-value', $request->query->get('manufacturer'));

        static::assertTrue($request->query->has('custom-flag'), 'Custom param "custom-flag" should be in query');
        static::assertTrue($request->query->getBoolean('custom-flag'));

        static::assertFalse($request->query->has('limit'), 'Standard param "limit" should NOT be in query');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStoreApiRequestsAreLeftToTheListener(): void
    {
        $request = new Request(['_criteria' => 'encoded-payload', 'custom-flag' => '1']);
        $request->setMethod(Request::METHOD_GET);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);

        $this->decoder->expects($this->never())->method('decode');

        $this->processor->prepare($request, new Criteria(), static::createStub(SalesChannelContext::class));

        static::assertSame('1', $request->query->get('custom-flag'), 'The value the listener copied is not overwritten');
    }

    public function testNothingIsReadWithTheNextMajorVersion(): void
    {
        $request = new Request(['_criteria' => 'encoded-payload']);
        $request->setMethod(Request::METHOD_GET);

        $this->decoder->expects($this->never())->method('decode');

        $this->processor->prepare($request, new Criteria(), static::createStub(SalesChannelContext::class));
    }
}
