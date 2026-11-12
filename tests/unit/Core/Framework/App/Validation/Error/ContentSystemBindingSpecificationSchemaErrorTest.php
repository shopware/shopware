<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemBindingSpecificationSchemaError::class)]
class ContentSystemBindingSpecificationSchemaErrorTest extends TestCase
{
    #[TestDox('lists every violation in the message')]
    public function testListsEveryViolationInMessage(): void
    {
        $error = new ContentSystemBindingSpecificationSchemaError(['first problem', 'second problem']);

        static::assertSame(
            "The following content-system binding specifications are invalid:\n- first problem\n- second problem",
            $error->getMessage()
        );
    }

    #[TestDox('reports the validation failed code and no parameters')]
    public function testReportsCodeAndParameters(): void
    {
        $error = new ContentSystemBindingSpecificationSchemaError(['first problem']);

        static::assertSame(AppException::VALIDATION_FAILED, $error->getErrorCode());
        static::assertSame([], $error->getParameters());
    }
}
