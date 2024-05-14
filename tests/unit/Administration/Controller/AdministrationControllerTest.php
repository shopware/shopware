<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AdministrationController::class)]
class AdministrationControllerTest extends TestCase
{
    private AdministrationController $administrationController;

    private PrefixFilesystem&MockObject $fileSystemOperatorMock;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testCheckCustomerEmailValidThrowErrorWithNullEmailParameter(): void
    {
        $this->expectException(RoutingException::class);

        $this->createInstance();
        $request = new Request();

        $this->administrationController->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithoutException(): void
    {
        $this->createInstance();
        $request = new Request([], ['email' => 'random@email.com']);

        $response = $this->administrationController->checkCustomerEmailValid($request, $this->context);
        static::assertIsString($response->getContent());
        static::assertEquals(
            ['isValid' => true],
            json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testCheckCustomerEmailValidWithConstraintException(): void
    {
        static::expectException(ConstraintViolationException::class);

        $customer = $this->mockCustomer();

        $this->createInstance(new CustomerCollection([$customer]));
        $request = new Request([], ['email' => 'random@email.com']);

        $this->administrationController->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelIdInvalid(): void
    {
        $this->expectException(RoutingException::class);

        $this->createInstance(new CustomerCollection(), true);
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => true]);

        $this->administrationController->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelIdValid(): void
    {
        $this->createInstance(new CustomerCollection(), true);
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => Uuid::randomHex()]);

        $response = $this->administrationController->checkCustomerEmailValid($request, $this->context);
        static::assertIsString($response->getContent());
        static::assertEquals(
            ['isValid' => true],
            json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelWithCustomerExistsInSalesChannel(): void
    {
        static::expectException(ConstraintViolationException::class);

        $customer = $this->mockCustomer();
        $salesChannel = $this->mockSalesChannel();
        $customer->setBoundSalesChannel($salesChannel);

        $this->createInstance(new CustomerCollection([$customer]), true);
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => $salesChannel->getId()]);

        $this->administrationController->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelWithCustomerExistsInAllSalesChannel(): void
    {
        static::expectException(ConstraintViolationException::class);

        $customer = $this->mockCustomer();

        $this->createInstance(new CustomerCollection([$customer]), true);
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => Uuid::randomHex()]);

        $this->administrationController->checkCustomerEmailValid($request, $this->context);
    }

    public function testPluginIndexReturnsNotFoundResponse(): void
    {
        $this->createInstance();

        $this->fileSystemOperatorMock->expects(static::once())
            ->method('read')
            ->with('bundles/foo/administration/index.html')
            ->willThrowException(new UnableToReadFile());
        $response = $this->administrationController->pluginIndex('foo');

        static::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertEquals('Plugin index.html not found', $response->getContent());
    }

    public function testPluginIndexReturnsUnchangedFileIfNoReplaceableStringIsFound(): void
    {
        $this->createInstance();

        $fileContent = '<html><head></head><body></body></html>';
        $this->fileSystemOperatorMock->expects(static::once())
            ->method('read')
            ->with('bundles/foo/administration/index.html')
            ->willReturn($fileContent);
        $response = $this->administrationController->pluginIndex('foo');

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals($fileContent, $response->getContent());
    }

    public function testPluginIndex(): void
    {
        $this->createInstance();

        $fileContent = '<html><head><base href="__$ASSET_BASE_PATH$__" /></head><body></body></html>';
        $this->fileSystemOperatorMock->expects(static::once())
            ->method('read')
            ->with('bundles/foo/administration/index.html')
            ->willReturn($fileContent);

        $this->fileSystemOperatorMock->expects(static::once())
            ->method('publicUrl')
            ->with('/')
            ->willReturn('http://localhost/bundles/');

        $response = $this->administrationController->pluginIndex('foo');

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringNotContainsString('__$ASSET_BASE_PATH$__', $content);
        static::assertStringContainsString('http://localhost/bundles/', $content);
    }

    private function mockSalesChannel(): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setName('New Sales Channel');

        return $salesChannel;
    }

    private function mockCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        return $customer;
    }

    private function createInstance(?CustomerCollection $collection = null, bool $isCustomerBoundToSalesChannel = false): void
    {
        $collection = $collection ?? new CustomerCollection();
        $this->fileSystemOperatorMock = $this->createMock(PrefixFilesystem::class);

        $this->administrationController = new AdministrationController(
            $this->createMock(TemplateFinder::class),
            $this->createMock(FirstRunWizardService::class),
            $this->createMock(SnippetFinderInterface::class),
            [],
            $this->createMock(KnownIpsCollectorInterface::class),
            $this->createMock(Connection::class),
            $this->createMock(EventDispatcherInterface::class),
            '',
            new StaticEntityRepository([$collection]),
            $this->createMock(EntityRepository::class),
            $this->createMock(HtmlSanitizer::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(ParameterBagInterface::class),
            new StaticSystemConfigService([
                'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => $isCustomerBoundToSalesChannel,
            ]),
            $this->fileSystemOperatorMock,
        );
    }
}
