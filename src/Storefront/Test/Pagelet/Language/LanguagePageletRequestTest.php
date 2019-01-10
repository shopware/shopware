<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet\Language;

use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletRequest;
use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletRequestResolver;
use Shopware\Storefront\Test\Page\PageRequestTestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class LanguagePageletRequestTest extends PageRequestTestCase
{
    /**
     * @var ContentLanguagePageletRequestResolver
     */
    private $requestResolver;

    protected function setUp()
    {
        parent::setUp();
        $this->requestResolver = new ContentLanguagePageletRequestResolver(
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

        static::assertInstanceOf(ContentLanguagePageletRequest::class, $request);
    }
}
