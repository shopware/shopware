<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResponseFields::class)]
class ResponseFieldsTest extends TestCase
{
    public function testIsAllowedReturnsTrueWhenTypeNotSet(): void
    {
        $responseFields = new ResponseFields();
        static::assertTrue($responseFields->isAllowed('someType', 'someProperty'));
    }

    public function testIsAllowedThrowsExceptionWhenIncludesTypeIsNotArray(): void
    {
        $this->expectExceptionObject(SalesChannelException::invalidType('The includes for type "someType" must be of the type array, string given'));

        /** @phpstan-ignore argument.type (for test purpose) */
        new ResponseFields(['someType' => 'notArray']);
    }

    public function testIsAllowedThrowsExceptionWhenExcludesTypeIsNotArray(): void
    {
        $this->expectExceptionObject(SalesChannelException::invalidType('The excludes for type "someType" must be of the type array, string given'));

        /** @phpstan-ignore argument.type (for test purpose) */
        new ResponseFields(excludes: ['someType' => 'notArray']);
    }

    public function testIsAllowedReturnsFalseWhenPropertyNotIncluded(): void
    {
        $responseFields = new ResponseFields(['someType' => ['anotherProperty']]);
        static::assertFalse($responseFields->isAllowed('someType', 'someProperty'));
    }

    public function testIsAllowedReturnsTrueWhenPropertyIsIncluded(): void
    {
        $responseFields = new ResponseFields(['someType' => ['someProperty']]);
        static::assertTrue($responseFields->isAllowed('someType', 'someProperty'));
    }

    public function testHasNestedReturnsTrueWhenPropertyHasPrefix(): void
    {
        $responseFields = new ResponseFields(['alias' => ['prefix.property']]);
        static::assertTrue($responseFields->hasNested('alias', 'prefix'));
    }

    public function testHasNestedReturnsFalseWhenPropertyDoesNotHavePrefix(): void
    {
        $responseFields = new ResponseFields(['alias' => ['otherprefix.property']]);
        static::assertFalse($responseFields->hasNested('alias', 'prefix'));
    }

    public function testFromRequestReadsTheQueryOfAGetRequest(): void
    {
        $request = new Request([
            'includes' => ['product' => ['id', 'name']],
            'excludes' => ['product' => ['name']],
        ]);

        $responseFields = ResponseFields::fromRequest($request);

        static::assertTrue($responseFields->isAllowed('product', 'id'));
        static::assertFalse($responseFields->isAllowed('product', 'name'));
        static::assertFalse($responseFields->isAllowed('product', 'description'));
    }

    public function testFromRequestReadsTheBodyOfAPostRequest(): void
    {
        $request = new Request(request: ['includes' => ['product' => ['id']]]);
        $request->setMethod(Request::METHOD_POST);

        $responseFields = ResponseFields::fromRequest($request);

        static::assertTrue($responseFields->isAllowed('product', 'id'));
        static::assertFalse($responseFields->isAllowed('product', 'name'));
    }

    public function testFromRequestAllowsEverythingWithoutParameters(): void
    {
        $request = new Request(request: ['includes' => null, 'excludes' => null]);
        $request->setMethod(Request::METHOD_POST);

        static::assertTrue(ResponseFields::fromRequest($request)->isAllowed('product', 'id'));
        static::assertTrue(ResponseFields::fromRequest(new Request())->isAllowed('product', 'id'));
    }

    public function testFromRequestRejectsIncludesThatAreNotAnArray(): void
    {
        $this->expectExceptionObject(SalesChannelException::invalidType('The includes must be of the type array, string given'));

        ResponseFields::fromRequest(new Request(['includes' => '{"product":["id"]}']));
    }

    public function testFromRequestRejectsExcludesThatAreNotAnArray(): void
    {
        $this->expectExceptionObject(SalesChannelException::invalidType('The excludes must be of the type array, string given'));

        ResponseFields::fromRequest(new Request(['excludes' => 'name']));
    }
}
