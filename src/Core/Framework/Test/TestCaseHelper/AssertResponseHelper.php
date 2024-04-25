<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

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

        TestCase::assertEquals($expected, $actual);
    }
}
