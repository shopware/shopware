<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\Currency;

use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletRequest;
use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CurrencyPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var ContentCurrencyPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new ContentCurrencyPageletRequestResolver(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('request_stack')
        );
    }

    public function testResolveArgument()
    {
        $httpRequest = $this->buildRequest();

        $request = $this->requestResolver->resolve(
            $httpRequest,
            new ArgumentMetadata('foo', self::class, false, false, null)
        );

        $request = iterator_to_array($request);
        $request = array_pop($request);

        static::assertInstanceOf(ContentCurrencyPageletRequest::class, $request);
    }
}
