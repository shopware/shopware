<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomFieldException::class)]
class CustomFieldExceptionTest extends TestCase
{
    public function testCustomFieldNameInvalid(): void
    {
        $name = 'test-name';
        $exception = CustomFieldException::customFieldNameInvalid($name);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomFieldException::CUSTOM_FIELD_NAME_INVALID, $exception->getErrorCode());
        static::assertSame('Invalid custom field name: It must begin with a letter or underscore, followed by letters, numbers, or underscores.', $exception->getMessage());
        static::assertSame(['field' => 'name', 'value' => $name], $exception->getParameters());
    }
}
