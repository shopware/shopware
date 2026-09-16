<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
class AssertResponseHelper
{
    /**
     * checks the responses for equality, but ignores the date header to make the test more stable
     */
    public static function assertResponseEquals(Response $expected, Response $actual): void
    {
        $expected->headers->set('date', null);
        $actual->headers->set('date', null);

        if ($expected instanceof StreamedResponse && $actual instanceof StreamedResponse) {
            // A StreamedResponse carries its body as a callback Closure that is never identical
            // between two instances. PHPUnit 12 compares closures by identity, so comparing the
            // whole object graph always fails; assert the observable state (status + headers) instead.
            TestCase::assertSame($expected->getStatusCode(), $actual->getStatusCode());
            TestCase::assertEquals($expected->headers->all(), $actual->headers->all());

            return;
        }

        TestCase::assertEquals($expected, $actual);
    }
}
