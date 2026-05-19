<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Context\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;
use Shopware\Core\Framework\App\Context\Payload\AppContextGatewayPayloadService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\Event\ContextGatewayCommandsCollectedEvent;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeCurrencyCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppContextGateway::class)]
class AppContextGatewayTest extends TestCase
{
    public function testProcess(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $expectedAppCriteria = new Criteria();
        $expectedAppCriteria->addFilter(new EqualsFilter('name', 'app_test'));
        $expectedAppCriteria->addFilter(new EqualsFilter('active', true));

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('app_test');
        $app->setContextGatewayUrl('https://example.com/gateway/context');

        $appResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            $expectedAppCriteria,
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->with($expectedAppCriteria, $context->getContext())
            ->willReturn($appResult);

        $expectedAppPayload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $appResponse = new AppContextGatewayResponse([['command' => 'context_change-currency', 'payload' => ['iso' => 'EUR']]]);

        $registry = new ContextGatewayCommandRegistry([new ChangeCurrencyCommandHandler($this->createMock(EntityRepository::class))]);

        $payloadService = $this->createMock(AppContextGatewayPayloadService::class);
        $payloadService
            ->expects($this->once())
            ->method('request')
            ->with('https://example.com/gateway/context', $expectedAppPayload, $app)
            ->willReturn($appResponse);

        $expectedCommands = new ContextGatewayCommandCollection([new ChangeCurrencyCommand('EUR')]);

        $executor = $this->createMock(ContextGatewayCommandExecutor::class);
        $executor
            ->expects($this->once())
            ->method('execute')
            ->with(static::equalTo($expectedCommands))
            ->willReturn(new ContextTokenResponse('hatoken'));

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $expectedEvent = new ContextGatewayCommandsCollectedEvent(
            $payload,
            $expectedCommands,
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::equalTo($expectedEvent));

        $gateway = new AppContextGateway(
            $payloadService,
            $executor,
            $registry,
            $appRepository,
            $eventDispatcher,
            $this->createMock(ExceptionLogger::class),
        );

        $response = $gateway->process($payload);

        static::assertSame('hatoken', $response->getToken());
    }

    public function testProcessWithAppNoContextGatewayUrlConfigured(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $expectedAppCriteria = new Criteria();
        $expectedAppCriteria->addFilter(new EqualsFilter('name', 'app_test'));
        $expectedAppCriteria->addFilter(new EqualsFilter('active', true));

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('app_test');
        $app->setContextGatewayUrl(null);

        $appResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            $expectedAppCriteria,
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->with($expectedAppCriteria, $context->getContext())
            ->willReturn($appResult);

        $logger = new ExceptionLogger(
            'test',
            true,
            $this->createMock(LoggerInterface::class),
        );

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $gateway = new AppContextGateway(
            $this->createMock(AppContextGatewayPayloadService::class),
            $this->createMock(ContextGatewayCommandExecutor::class),
            $this->createMock(ContextGatewayCommandRegistry::class),
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $logger,
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Gateway "context" is not configured for app "app_test". Please check the manifest file');

        $gateway->process($payload);
    }

    public function testProcessWithAppNotFound(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $expectedAppCriteria = new Criteria();
        $expectedAppCriteria->addFilter(new EqualsFilter('name', 'app_test'));
        $expectedAppCriteria->addFilter(new EqualsFilter('active', true));

        $appResult = new EntitySearchResult(
            'app',
            0,
            new AppCollection(),
            null,
            $expectedAppCriteria,
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->with($expectedAppCriteria, $context->getContext())
            ->willReturn($appResult);

        $logger = new ExceptionLogger(
            'test',
            true,
            $this->createMock(LoggerInterface::class),
        );

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $gateway = new AppContextGateway(
            $this->createMock(AppContextGatewayPayloadService::class),
            $this->createMock(ContextGatewayCommandExecutor::class),
            $this->createMock(ContextGatewayCommandRegistry::class),
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $logger,
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Could not find app with name "app_test"');

        $gateway->process($payload);
    }

    public function testProcessWithEmptyAppResponse(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('app_test');
        $app->setContextGatewayUrl('https://example.com/gateway/context');

        $appResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            new Criteria(),
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($appResult);

        $payloadService = $this->createMock(AppContextGatewayPayloadService::class);
        $payloadService
            ->expects($this->once())
            ->method('request')
            ->willReturn(null);

        $logger = new ExceptionLogger(
            'test',
            true,
            $this->createMock(LoggerInterface::class),
        );

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $gateway = new AppContextGateway(
            $payloadService,
            $this->createMock(ContextGatewayCommandExecutor::class),
            $this->createMock(ContextGatewayCommandRegistry::class),
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $logger,
        );

        $this->expectExceptionObject(AppException::gatewayRequestFailed('app_test', 'context'));

        $gateway->process($payload);
    }

    public function testProcessWithMissingResponsePayload(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('app_test');
        $app->setContextGatewayUrl('https://example.com/gateway/context');

        $appResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            new Criteria(),
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($appResult);

        /** @phpstan-ignore-next-line – intentionally testing invalid structure (missing 'payload') */
        $appResponse = new AppContextGatewayResponse([['command' => 'context_change-currency']]);

        $payloadService = $this->createMock(AppContextGatewayPayloadService::class);
        $payloadService
            ->expects($this->once())
            ->method('request')
            ->willReturn($appResponse);

        $logger = new ExceptionLogger(
            'test',
            true,
            $this->createMock(LoggerInterface::class),
        );

        $registry = new ContextGatewayCommandRegistry([new ChangeCurrencyCommandHandler($this->createMock(EntityRepository::class))]);

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $gateway = new AppContextGateway(
            $payloadService,
            $this->createMock(ContextGatewayCommandExecutor::class),
            $registry,
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $logger,
        );

        $this->expectExceptionObject(GatewayException::payloadInvalid('context_change-currency'));

        $gateway->process($payload);
    }

    public function testProcessWithUnknownHandlerInAppResponse(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('app_test');
        $app->setContextGatewayUrl('https://example.com/gateway/context');

        $appResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            new Criteria(),
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($appResult);

        $appResponse = new AppContextGatewayResponse(
            [
                ['command' => 'context_foo-bar', 'payload' => ['foo' => 'bar']], // wrong handler id
            ]
        );

        $payloadService = $this->createMock(AppContextGatewayPayloadService::class);
        $payloadService
            ->expects($this->once())
            ->method('request')
            ->willReturn($appResponse);

        $logger = new ExceptionLogger(
            'test',
            true,
            $this->createMock(LoggerInterface::class),
        );

        $registry = new ContextGatewayCommandRegistry([new ChangeCurrencyCommandHandler($this->createMock(EntityRepository::class))]);

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $gateway = new AppContextGateway(
            $payloadService,
            $this->createMock(ContextGatewayCommandExecutor::class),
            $registry,
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $logger,
        );

        $this->expectExceptionObject(GatewayException::handlerNotFound('context_foo-bar'));

        $gateway->process($payload);
    }

    public function testProcessWithMisconfiguredCommandClassInResponse(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('app_test');
        $app->setContextGatewayUrl('https://example.com/gateway/context');

        $appResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            new Criteria(),
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($appResult);

        $appResponse = new AppContextGatewayResponse(
            [
                ['command' => 'context_foo-bar', 'payload' => ['foo' => 'bar']],
            ]
        );

        $payloadService = $this->createMock(AppContextGatewayPayloadService::class);
        $payloadService
            ->expects($this->once())
            ->method('request')
            ->willReturn($appResponse);

        $logger = new ExceptionLogger(
            'test',
            true,
            $this->createMock(LoggerInterface::class),
        );

        $registry = $this->createMock(ContextGatewayCommandRegistry::class);
        $registry
            ->method('hasAppCommand')
            ->willReturn(true);

        $registry
            ->method('getAppCommand')
            ->willReturn(\stdClass::class); // non-command class response

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $gateway = new AppContextGateway(
            $payloadService,
            $this->createMock(ContextGatewayCommandExecutor::class),
            $registry,
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $logger,
        );

        $this->expectExceptionObject(GatewayException::handlerNotFound('context_foo-bar'));

        $gateway->process($payload);
    }

    public function testProcessWithBadPayloadFromAppServer(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag(['appName' => 'app_test', 'foo' => 'bar']);

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('app_test');
        $app->setContextGatewayUrl('https://example.com/gateway/context');

        $appResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            new Criteria(),
            $context->getContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($appResult);

        $appResponse = new AppContextGatewayResponse(
            [
                ['command' => 'context_foo-bar', 'payload' => ['foo' => 'bar']], // wrong payload
            ]
        );

        $payloadService = $this->createMock(AppContextGatewayPayloadService::class);
        $payloadService
            ->expects($this->once())
            ->method('request')
            ->willReturn($appResponse);

        $logger = new ExceptionLogger(
            'test',
            true,
            $this->createMock(LoggerInterface::class),
        );

        $registry = $this->createMock(ContextGatewayCommandRegistry::class);
        $registry
            ->method('hasAppCommand')
            ->willReturn(true);

        $registry
            ->method('getAppCommand')
            ->willReturn(ChangeCurrencyCommand::class); // class can not handle "foo" payload

        $payload = new ContextGatewayPayloadStruct($cart, $context, $data);

        $gateway = new AppContextGateway(
            $payloadService,
            $this->createMock(ContextGatewayCommandExecutor::class),
            $registry,
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $logger,
        );

        $this->expectExceptionObject(GatewayException::payloadInvalid('context_foo-bar'));

        $gateway->process($payload);
    }
}
