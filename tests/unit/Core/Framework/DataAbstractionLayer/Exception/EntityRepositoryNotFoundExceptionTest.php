<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityRepositoryNotFoundException::class)]
class EntityRepositoryNotFoundExceptionTest extends TestCase
{
    public function testGetStatusCodeWillReturn400(): void
    {
        $exception = new EntityRepositoryNotFoundException(TestEntity::class);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testGetErrorCodeWillReturnStringWithNotFoundText(): void
    {
        $exception = new EntityRepositoryNotFoundException(TestEntity::class);

        static::assertStringContainsString('not_found', strtolower($exception->getErrorCode()));
    }
}

/**
 * @internal
 */
class TestEntity extends Entity
{
}
