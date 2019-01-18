<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\Header;

use Shopware\Storefront\Pagelet\Header\ContentHeaderPageletRequest;
use Shopware\Storefront\Pagelet\Header\ContentHeaderPageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class HeaderPageletRequestTest extends PageRequestTestCase
{
    /**
     * @var ContentHeaderPageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new ContentHeaderPageletRequestResolver(
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

        static::assertInstanceOf(ContentHeaderPageletRequest::class, $request);
    }
}
