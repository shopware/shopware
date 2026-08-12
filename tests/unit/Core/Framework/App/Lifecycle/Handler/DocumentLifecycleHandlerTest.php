<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\DocumentLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DocumentLifecycleHandler::class)]
final class DocumentLifecycleHandlerTest extends TestCase
{
    public function testInstallWithoutDocumentsBlockDoesNotThrow(): void
    {
        $handler = $this->buildHandler([]);

        $handler->install($this->buildContext());

        $this->addToAssertionCount(1);
    }

    public function testInstallWithUniqueIdentifierDoesNotThrow(): void
    {
        $handler = $this->buildHandler([]);

        $handler->install($this->buildContext($this->documentTypes()));

        $this->addToAssertionCount(1);
    }

    public function testInstallThrowsWhenIdentifierShadowsCoreDocumentType(): void
    {
        $handler = $this->buildHandler([]);

        $this->expectExceptionObject(AppException::documentTypeShadowsCoreType('invoice'));

        $handler->install($this->buildContext($this->documentTypesWithCoreCollision()));
    }

    public function testInstallThrowsWhenIdentifierIsClaimedByAnotherApp(): void
    {
        $handler = $this->buildHandler(['swag_warranty' => 'OtherApp']);

        $this->expectExceptionObject(AppException::documentTypeAlreadyRegistered('swag_warranty', 'OtherApp'));

        $handler->install($this->buildContext($this->documentTypes()));
    }

    public function testUpdateAppliesTheSameCollisionCheckAsInstall(): void
    {
        $handler = $this->buildHandler(['swag_warranty' => 'OtherApp']);

        $this->expectExceptionObject(AppException::documentTypeAlreadyRegistered('swag_warranty', 'OtherApp'));

        $handler->update($this->buildContext($this->documentTypes()));
    }

    /**
     * @param array<string, string> $claimedBy
     */
    private function buildHandler(array $claimedBy): DocumentLifecycleHandler
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($claimedBy);

        return new DocumentLifecycleHandler($connection);
    }

    private function buildContext(string $documents = ''): AppPersistContext
    {
        $app = new AppEntity();
        $app->setId('app-id-123');
        $app->setName('TestApp');

        return new AppPersistContext(
            manifest: $this->manifest($documents),
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new Filesystem(''),
            defaultLocale: 'en-GB',
        );
    }

    private function manifest(string $documents = ''): Manifest
    {
        return Manifest::createFromXml(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>DocumentLifecycleTestApp</name>
                    <label>Document Lifecycle Test App</label>
                    <author>shopware AG</author>
                    <copyright>(c) shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                {$documents}
            </manifest>
            XML);
    }

    private function documentTypes(): string
    {
        return <<<'XML'
            <documents>
                <document-type>
                    <identifier>swag_warranty</identifier>
                    <label>Warranty certificate</label>
                    <label lang="de-DE">Garantieschein</label>
                    <formats>
                        <format>html</format>
                        <format>pdf</format>
                    </formats>
                </document-type>
            </documents>
            XML;
    }

    private function documentTypesWithCoreCollision(): string
    {
        return <<<'XML'
            <documents>
                <document-type>
                    <identifier>invoice</identifier>
                    <label>Invoice</label>
                    <formats>
                        <format>pdf</format>
                    </formats>
                </document-type>
            </documents>
            XML;
    }
}
