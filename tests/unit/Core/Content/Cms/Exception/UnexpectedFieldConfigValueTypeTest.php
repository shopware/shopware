<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Exception\UnexpectedFieldConfigValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(UnexpectedFieldConfigValueType::class)]
class UnexpectedFieldConfigValueTypeTest extends TestCase
{
    public function testUnexpectedFieldConfigValueType(): void
    {
        $exception = new UnexpectedFieldConfigValueType('name', 'string', 'int');
        static::assertSame('CONTENT__CMS_UNEXPECTED_VALUE_TYPE', $exception->getErrorCode());
        static::assertSame('Expected to load value of "name" with type "string", but value with type "int" given.', $exception->getMessage());
    }
}
