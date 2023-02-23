<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ProfileSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\Test\StaticTranslator;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Test\Controller\fixtures\BundleFixture;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
class StorefrontControllerTest extends TestCase
{
    use KernelTestBehaviour;

    private const URL = 'eb873540-a5eb-413d-bbbf-edfa0e3782cb';
    private const MESSAGE = '4b07ea7b-24e1-4c07-9035-92010f56395d';

    private string $cacheDir;

    private FilesystemCache $cache;

    public function setUp(): void
    {
        $this->cacheDir = $this->getKernel()->getCacheDir() . '/twig_test_' . microtime();
        $this->cache = new FilesystemCache($this->cacheDir);
    }

    /**
     * @dataProvider cartProvider
     */
    public function testAddCartErrorsAddsUrlToSalutationErrors(Cart $cart): void
    {
        $container = static::createStub(ContainerInterface::class);

        $container->method('get')
            ->willReturnMap([
                $this->getRequestStack(),
                $this->getRouter(),
                $this->getTranslator($cart->getErrors()),
            ]);

        $controller = new TestController();

        $controller->setContainer($container);
        $controller->addCartErrors($cart);

        static::assertNotEmpty($cart->getErrors()->getElements());
    }

    public function testStorefrontRenderViewinheritance(): void
    {
        $twig = $this->createFinder([
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1/'),
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
        ]);

        $controller = new TestController();
        $controller->setTwig($twig);
        $controller->setContainer($this->getContainer());
        $controller->setTemplateFinder($twig->getExtension(NodeExtension::class)->getFinder());

        $rendered = $controller->testRenderViewInheritance('@Storefront/storefront/base.html.twig');

        static::assertEquals('inherited', $rendered);
    }

    public function testStorefrontPluginTemplatePaths(): void
    {
        $twig = $this->createFinder([
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1/'),
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
        ]);

        $controller = new TestController();
        $controller->setTwig($twig);
        $controller->setContainer($this->getContainer());
        $controller->setTemplateFinder($twig->getExtension(NodeExtension::class)->getFinder());

        $rendered = $controller->testRenderViewInheritance('@Storefront/storefront/page/plugin/index.html.twig');

        static::assertEquals('plugin', $rendered);
    }

    public static function cartProvider(): \Generator
    {
        $cart = new Cart('test');
        $cart->setErrors(new ErrorCollection(self::getErrors()));

        yield 'cart with salutation errors' => [
            $cart,
        ];
    }

    /**
     * @return array{
     *     0: 'request_stack',
     *     1: ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
     *     2: Stub
     * }
     */
    private function getRequestStack(): array
    {
        return [
            'request_stack',
            ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            static::createStub(RequestStack::class),
        ];
    }

    /**
     * @return array{
     *     0: 'router',
     *     1: ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
     *     2: Stub
     * }
     */
    private function getRouter(): array
    {
        return [
            'router',
            ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            static::createConfiguredMock(
                RouterInterface::class,
                ['generate' => self::URL],
            ),
        ];
    }

    /**
     * @return array{
     *     0: 'translator',
     *     1: ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
     *     2: StaticTranslator
     * }
     */
    private function getTranslator(ErrorCollection $errors): array
    {
        $translations = [];
        foreach ($errors->getElements() as $error) {
            $translations['checkout.' . $error->getMessageKey()] = self::MESSAGE;
        }

        $translator = new StaticTranslator($translations);

        return [
            'translator',
            ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            $translator,
        ];
    }

    /**
     * @return array{
     *     0: ProfileSalutationMissingError,
     *     1: BillingAddressSalutationMissingError,
     *     2: ShippingAddressSalutationMissingError
     * }
     */
    private static function getErrors(): array
    {
        $customer = new CustomerEntity();
        $customer->setId('1');
        $customer->setCustomerNumber('');
        $customer->setFirstName('');
        $customer->setLastName('');

        $address = new CustomerAddressEntity();
        $address->setId('');
        $address->setFirstName('');
        $address->setLastName('');
        $address->setZipcode('');
        $address->setCity('');

        return [
            new ProfileSalutationMissingError($customer),
            new BillingAddressSalutationMissingError($address),
            new ShippingAddressSalutationMissingError($address),
        ];
    }

    /**
     * @param array<BundleFixture> $bundles
     */
    private function createFinder(array $bundles): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');

        /** @var BundleFixture $bundle */
        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
        }

        $twig = new Environment($loader, ['cache' => $this->cache]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            $this->cacheDir,
            new NamespaceHierarchyBuilder([
                new BundleHierarchyBuilder(
                    $kernel,
                    $this->getContainer()->get(Connection::class)
                ),
            ])
        );

        $twig->addExtension(new NodeExtension($templateFinder));
        $twig->getExtension(NodeExtension::class)->getFinder();

        return $twig;
    }
}

/**
 * @internal
 */
class TestController extends StorefrontController
{
    private TemplateFinder $templateFinder;

    public function addCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
        parent::addCartErrors($cart, $filter);
    }

    public function addFlash(string $type, mixed $message): void
    {
        // NOOP
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function testRenderViewInheritance(string $view, array $parameters = []): string
    {
        return parent::renderView($view, $parameters);
    }

    public function setTemplateFinder(TemplateFinder $templateFinder): void
    {
        $this->templateFinder = $templateFinder;
    }

    protected function getTemplateFinder(): TemplateFinder
    {
        return $this->templateFinder;
    }
}
