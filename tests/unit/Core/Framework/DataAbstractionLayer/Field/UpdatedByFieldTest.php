<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdatedByField::class)]
class UpdatedByFieldTest extends TestCase
{
    public function testGetAllowedWriteScopesDefaultsToSystemScopeBeforeV680(): void
    {
        Feature::fake([], function (): void {
            $field = new UpdatedByField();

            static::assertSame([Context::SYSTEM_SCOPE], $field->getAllowedWriteScopes());
        });
    }

    public function testGetAllowedWriteScopesUsesExplicitScopes(): void
    {
        $field = new UpdatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]);

        static::assertSame([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE], $field->getAllowedWriteScopes());
    }

    public function testGetAllowedWriteScopesDefaultsToCrudScopeInV680(): void
    {
        Feature::fake(['v6.8.0.0'], function (): void {
            $field = new UpdatedByField();

            static::assertSame([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE], $field->getAllowedWriteScopes());
        });
    }

    public function testExplicitScopesStayUntouchedInV680(): void
    {
        Feature::fake(['v6.8.0.0'], function (): void {
            $field = new UpdatedByField([Context::SYSTEM_SCOPE]);

            static::assertSame([Context::SYSTEM_SCOPE], $field->getAllowedWriteScopes());
        });
    }
}
