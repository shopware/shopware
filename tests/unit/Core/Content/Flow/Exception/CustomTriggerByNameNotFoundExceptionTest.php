<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Exception\CustomTriggerByNameNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomTriggerByNameNotFoundException::class)]
class CustomTriggerByNameNotFoundExceptionTest extends TestCase
{
    public function testException(): void
    {
        $exception = new CustomTriggerByNameNotFoundException('event_name_test');
        static::assertEquals('The provided event name event_name_test is invalid or uninstalled and no custom trigger could be found.', $exception->getMessage());
        static::assertEquals('ADMINISTRATION__CUSTOM_TRIGGER_BY_NAME_NOT_FOUND', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
    }
}
