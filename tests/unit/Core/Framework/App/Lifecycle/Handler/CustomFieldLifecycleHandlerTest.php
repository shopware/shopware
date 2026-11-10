<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\CustomFieldLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\CustomField\CustomFieldXmlLoader;
use Shopware\Core\System\CustomField\Xml\CustomFields;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFieldLifecycleHandler::class)]
class CustomFieldLifecycleHandlerTest extends TestCase
{
    private const APP_WITH_CUSTOM_FIELDS = __DIR__ . '/_fixtures/app-with-custom-fields';

    private const APP_WITHOUT_CUSTOM_FIELDS = __DIR__ . '/_fixtures/app-without-custom-fields';

    private const FIXTURE_FILE = self::APP_WITH_CUSTOM_FIELDS . '/Resources/config/custom-fields.xml';

    public function testPersistWithExternalFile(): void
    {
        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->once())
            ->method('sync')
            ->with(
                static::callback(function (CustomFields $customFields): bool {
                    $sets = $customFields->getCustomFieldSets();

                    return \count($sets) === 2 && $sets[0]->getName() === 'test_set';
                }),
                'app-id-123',
                'TestApp',
                static::isInstanceOf(Context::class),
                'en-GB'
            );

        $handler = new CustomFieldLifecycleHandler($sharedPersister, new NullLogger());
        $handler->install($this->createContext(self::APP_WITH_CUSTOM_FIELDS));
    }

    public function testPersistWithoutFileAndWithoutManifest(): void
    {
        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->once())
            ->method('sync')
            ->with(
                static::callback(function (CustomFields $customFields): bool {
                    return $customFields->getCustomFieldSets() === [];
                }),
                'app-id-123',
                'TestApp',
                static::isInstanceOf(Context::class)
            );

        $handler = new CustomFieldLifecycleHandler($sharedPersister, new NullLogger());
        $handler->install($this->createContext(self::APP_WITHOUT_CUSTOM_FIELDS));
    }

    public function testPersistWithInlineManifestCustomFields(): void
    {
        $inlineCustomFields = CustomFieldXmlLoader::load(self::FIXTURE_FILE);

        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->once())
            ->method('sync')
            ->with($inlineCustomFields, 'app-id-123', 'TestApp');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $handler = new CustomFieldLifecycleHandler($sharedPersister, $logger);
        $handler->update($this->createContext(self::APP_WITHOUT_CUSTOM_FIELDS, $inlineCustomFields));
    }

    public function testExternalFileTakesPrecedenceOverInlineManifestCustomFields(): void
    {
        $inlineCustomFields = CustomFields::fromArray([]);

        $sharedPersister = $this->createMock(CustomFieldSetPersister::class);
        $sharedPersister->expects($this->once())
            ->method('sync')
            ->with(
                static::callback(function (CustomFields $customFields) use ($inlineCustomFields): bool {
                    return $customFields !== $inlineCustomFields && \count($customFields->getCustomFieldSets()) === 2;
                }),
                'app-id-123',
                'TestApp'
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('The inline definition in manifest.xml is ignored.'), ['appName' => 'TestApp']);

        $handler = new CustomFieldLifecycleHandler($sharedPersister, $logger);
        $handler->install($this->createContext(self::APP_WITH_CUSTOM_FIELDS, $inlineCustomFields));
    }

    private function createContext(string $appDir, ?CustomFields $inlineCustomFields = null): AppPersistContext
    {
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getCustomFields')->willReturn($inlineCustomFields);

        $app = new AppEntity();
        $app->setId('app-id-123');
        $app->setUniqueIdentifier('app-id-123');
        $app->setName('TestApp');

        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new Filesystem($appDir),
            defaultLocale: 'en-GB',
        );
    }
}
