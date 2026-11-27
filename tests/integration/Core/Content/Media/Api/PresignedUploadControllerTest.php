<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class PresignedUploadControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testPrepareRequiresMediaUpdatePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['media:read']);
        $browser->jsonRequest('POST', '/api/_action/media/presign-upload', [
            'fileName' => 'presign-acl-test',
            'extension' => 'png',
            'mimeType' => 'image/png',
            'mediaId' => Uuid::randomHex(),
        ]);

        $this->assertMissingPrivileges(['media:update'], $browser->getResponse());
    }

    public function testPrepareOfNewMediaRequiresMediaCreatePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['media:read', 'media:update']);
        $browser->jsonRequest('POST', '/api/_action/media/presign-upload', [
            'fileName' => 'presign-acl-test',
            'extension' => 'png',
            'mimeType' => 'image/png',
        ]);

        $this->assertMissingPrivileges(['media:create'], $browser->getResponse());
    }

    public function testFinalizeRequiresMediaUpdatePrivilege(): void
    {
        $mediaId = Uuid::randomHex();

        $browser = $this->getBrowser(true, [], ['media:read']);
        $browser->jsonRequest('POST', \sprintf('/api/_action/media/%s/finalize-upload', $mediaId), [
            'fileName' => 'presign-acl-test',
            'extension' => 'png',
            'mimeType' => 'image/png',
            'path' => 'media/ab/cd/presign-acl-test.png',
        ]);

        $this->assertMissingPrivileges(['media:update'], $browser->getResponse());
    }

    public function testPrepareOfReplacementPassesTheAclCheckWithMediaUpdatePrivilege(): void
    {
        $mediaId = Uuid::randomHex();

        $browser = $this->getBrowser(true, [], ['media:read', 'media:update']);
        $browser->jsonRequest('POST', '/api/_action/media/presign-upload', [
            'fileName' => 'presign-acl-test',
            'extension' => 'png',
            'mimeType' => 'image/png',
            'mediaId' => $mediaId,
        ]);

        $this->assertErrorCode(Response::HTTP_NOT_FOUND, MediaException::MEDIA_NOT_FOUND, $browser->getResponse());
    }

    public function testPrepareOfNewMediaPassesTheAclCheckWithMediaCreateAndUpdatePrivileges(): void
    {
        $browser = $this->getBrowser(true, [], ['media:read', 'media:create', 'media:update']);
        $browser->jsonRequest('POST', '/api/_action/media/presign-upload', [
            'fileName' => 'presign-acl-test',
            'extension' => 'png',
            'mimeType' => 'image/png',
        ]);

        $this->assertErrorCode(Response::HTTP_BAD_REQUEST, MediaException::MEDIA_PRESIGNED_UPLOAD_DISABLED, $browser->getResponse());
    }

    private function assertErrorCode(int $expectedStatus, string $expectedCode, Response $response): void
    {
        $content = (string) $response->getContent();

        static::assertSame($expectedStatus, $response->getStatusCode(), $content);
        static::assertSame($expectedCode, json_decode($content, true, flags: \JSON_THROW_ON_ERROR)['errors'][0]['code'], $content);
    }

    /**
     * @param list<string> $expectedPrivileges
     */
    private function assertMissingPrivileges(array $expectedPrivileges, Response $response): void
    {
        $content = (string) $response->getContent();
        $error = json_decode($content, true, flags: \JSON_THROW_ON_ERROR)['errors'][0];

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $error['code'], $content);
        static::assertSame($expectedPrivileges, json_decode($error['detail'], true, flags: \JSON_THROW_ON_ERROR)['missingPrivileges'], $content);
    }
}
