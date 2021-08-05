<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ProfileSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class StorefrontControllerTest extends TestCase
{
    private const URL = 'eb873540-a5eb-413d-bbbf-edfa0e3782cb';
    private const MESSAGE = '4b07ea7b-24e1-4c07-9035-92010f56395d';

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
    }

    public function cartProvider(): \Generator
    {
        yield 'cart with salutation errors' => [
            $this->createConfiguredMock(
                Cart::class,
                ['getErrors' => new ErrorCollection($this->getErrors())]
            ),
        ];
    }

    private function getRequestStack(): array
    {
        return [
            'request_stack',
            ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            static::createStub(RequestStack::class),
        ];
    }

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

    private function getTranslator(ErrorCollection $errors): array
    {
        $argumentValidation = array_map(static function (Error $error): array {
            return [
                static::equalTo('checkout.' . $error->getMessageKey()),
                static::callback(static function (array $parameters): bool {
                    return \array_key_exists('%url%', $parameters) && $parameters['%url%'] === self::URL;
                }),
            ];
        }, $errors->getElements());

        $translator = static::createMock(TranslatorInterface::class);
        $translator->expects(static::exactly(\count($errors)))
            ->method('trans')
            ->withConsecutive(...$argumentValidation)
            ->willReturn(self::MESSAGE);

        return [
            'translator',
            ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            $translator,
        ];
    }

    private function getErrors(): array
    {
        return [
            new ProfileSalutationMissingError(static::createStub(CustomerEntity::class)),
            new BillingAddressSalutationMissingError(static::createStub(CustomerAddressEntity::class)),
            new ShippingAddressSalutationMissingError(static::createStub(CustomerAddressEntity::class)),
        ];
    }
}

class TestController extends StorefrontController
{
    public function addCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
        parent::addCartErrors($cart, $filter);
    }

    public function addFlash(string $type, $message): void
    {
        // NOOP
    }
}
