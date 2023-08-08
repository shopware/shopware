<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Controller\Exception\StorefrontException;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\Exception\StorefrontException
 */
class StorefrontExceptionTest extends TestCase
{
    public function testCannotRenderView(): void
    {
        $parameters = [
            'param' => 'Param',
            'context' => Context::createDefaultContext(),
        ];

        $res = StorefrontException::cannotRenderView('test.html.twig', 'Error message', $parameters);

        static::assertEquals(500, $res->getStatusCode());
        static::assertEquals('STOREFRONT__CAN_NOT_RENDER_VIEW', $res->getErrorCode());
        static::assertEquals('Can not render test.html.twig view: Error message with these parameters: {"param":"Param"}', $res->getMessage());
    }

    public function testUnSupportStorefrontResponse(): void
    {
        $res = StorefrontException::unSupportStorefrontResponse();

        static::assertEquals(500, $res->getStatusCode());
        static::assertEquals('STOREFRONT__UN_SUPPORT_STOREFRONT_RESPONSE', $res->getErrorCode());
        static::assertEquals('Symfony render implementation changed. Providing a response is no longer supported', $res->getMessage());
    }

    public function testDontHaveTwigInjected(): void
    {
        $res = StorefrontException::dontHaveTwigInjected('Example\Class');

        static::assertEquals(500, $res->getStatusCode());
        static::assertEquals('STOREFRONT__CLASS_DONT_HAVE_TWIG_INJECTED', $res->getErrorCode());
        static::assertEquals('Class Example\Class does not have twig injected. Add to your service definition a method call to setTwig with the twig instance', $res->getMessage());
    }
}
