<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Home;

use Shopware\Storefront\Page\ContentHome\ContentHomePageRequest;
use Shopware\Storefront\Page\ContentHome\ContentHomePageRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ContentHomePageRequestTest extends PageRequestTestCase
{
    /**
     * @var ContentHomePageRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = $this->getContainer()->get(ContentHomePageRequestResolver::class);
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

        static::assertInstanceOf(ContentHomePageRequest::class, $request);
    }
}
