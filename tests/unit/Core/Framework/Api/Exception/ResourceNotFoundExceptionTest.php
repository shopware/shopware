<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResourceNotFoundException::class)]
class ResourceNotFoundExceptionTest extends TestCase
{
    #[TestDox('the primary key pairs are rendered as key(value) into the message')]
    public function testMessageRendersPrimaryKey(): void
    {
        $exception = new ResourceNotFoundException('product', ['id' => 'p-1', 'versionId' => 'v-1']);

        static::assertSame('FRAMEWORK__RESOURCE_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(
            'The product resource with the following primary key was not found: id(p-1) versionId(v-1)',
            $exception->getMessage()
        );
        static::assertSame(['id' => 'p-1', 'versionId' => 'v-1'], $exception->getParameter('primaryKey'));
    }
}
