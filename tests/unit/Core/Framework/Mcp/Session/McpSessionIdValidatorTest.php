<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(McpSessionIdValidator::class)]
class McpSessionIdValidatorTest extends TestCase
{
    private McpSessionIdValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new McpSessionIdValidator();
    }

    public function testValidatePassesWhenHeaderIsAbsent(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(new Request());
    }

    public function testValidatePassesForValidUuid(): void
    {
        $this->expectNotToPerformAssertions();

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, Uuid::v7()->toRfc4122());

        $this->validator->validate($request);
    }

    public function testValidateThrowsForMalformedSessionId(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, 'not-a-uuid');

        $this->expectExceptionObject(McpException::invalidSessionId());

        $this->validator->validate($request);
    }
}
