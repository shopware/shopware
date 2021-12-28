<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\Tests\Check;

use PHPUnit\Framework\TestCase;
use Shopware\RoaveBackwardCompatibility\Check\AnnotationDiff;

class AnnotationDiffTest extends TestCase
{
    public function testEmptyComment(): void
    {
        $changes = AnnotationDiff::diff('', '', '');
        static::assertEquals(0, $changes->count());
    }

    public function testRouteAnnotationPathGotChanged(): void
    {
        $changes = AnnotationDiff::diff(
            '',
            '/** @Route("/account/order/{deepLinkCode}", name="frontend.account.order.single.page", options={"seo"="false"}, methods={"GET", "POST"}) */',
            '/** @Route("/account/{deepLinkCode}", name="frontend.account.order.single.page", options={"seo"="false"}, methods={"GET", "POST"}) */',
        );

        static::assertEquals(1, $changes->count());
        static::assertSame('[BC] CHANGED: The annotation "Route" parameter "path" has been changed on  from "/account/order/{deepLinkCode}" to "/account/{deepLinkCode}"', iterator_to_array($changes->getIterator())[0]->__toString());
    }

    public function testRouteAnnotationNameGotChanged(): void
    {
        $changes = AnnotationDiff::diff(
            '',
            '/** @Route("/account/order/{deepLinkCode}", name="frontend.account.order.single.page", options={"seo"="false"}, methods={"GET", "POST"}) */',
            '/** @Route("/account/order/{deepLinkCode}", name="frontend.account.order.single", options={"seo"="false"}, methods={"GET", "POST"}) */',
        );

        static::assertEquals(1, $changes->count());
        static::assertSame('[BC] REMOVED: The annotation "Route" has been removed on ""', iterator_to_array($changes->getIterator())[0]->__toString());
    }

    public function testRouteAnnotationGotRemoved(): void
    {
        $changes = AnnotationDiff::diff(
            '',
            '/** @Route("/account/order/{deepLinkCode}", name="frontend.account.order.single.page", options={"seo"="false"}, methods={"GET", "POST"}) */',
            '/** */',
        );

        static::assertEquals(1, $changes->count());
        static::assertSame('[BC] REMOVED: The annotation "Route" has been removed on ""', iterator_to_array($changes->getIterator())[0]->__toString());
    }
}
