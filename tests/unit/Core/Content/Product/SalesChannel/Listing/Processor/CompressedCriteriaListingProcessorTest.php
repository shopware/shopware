<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompressedCriteriaListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
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
        $this->processor->prepare($request, new Criteria(), $this->createMock(SalesChannelContext::class));
    }

    public function testPrepareIgnoredMissingCriteria(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);

        $this->decoder->expects($this->never())->method('decode');

        $this->processor->prepare($request, new Criteria(), $this->createMock(SalesChannelContext::class));
    }

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

        $this->processor->prepare($request, new Criteria(), $this->createMock(SalesChannelContext::class));

        static::assertTrue($request->query->has('manufacturer'), 'Custom param "manufacturer" should be in query');
        static::assertSame('param-value', $request->query->get('manufacturer'));

        static::assertTrue($request->query->has('custom-flag'), 'Custom param "custom-flag" should be in query');
        static::assertTrue($request->query->getBoolean('custom-flag'));

        static::assertFalse($request->query->has('limit'), 'Standard param "limit" should NOT be in query');
    }
}
