<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeLanguageRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ContextController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class ContextControllerUnitTest extends TestCase
{
    public function testSwitchLangNoArgument(): void
    {
        $controller = new ContextController(
            $this->createMock(ContextSwitchRoute::class),
            $this->createMock(RequestStack::class),
            $this->createMock(RouterInterface::class)
        );

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Parameter "languageId" is missing.');

        $controller->switchLanguage(new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testSwitchLangNotFound(): void
    {
        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects(static::once())->method('switchContext')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );
        $controller = new ContextController(
            $contextSwitchRoute,
            $this->createMock(RequestStack::class),
            $this->createMock(RouterInterface::class)
        );

        $notExistingLang = Uuid::randomHex();

        $this->expectException(LanguageNotFoundException::class);
        $this->expectExceptionMessage('The language "' . $notExistingLang . '" was not found');

        $controller->switchLanguage(
            new Request([], ['languageId' => $notExistingLang]),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public function testSwitchCustomerChange(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new SalesChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setSalesChannelDomains(new SalesChannelDomainCollection([$scDomain]));

        $changeLangMock = $this->createMock(AbstractChangeLanguageRoute::class);

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects(static::once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects(static::once())->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects(static::exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects(static::once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock
        );

        $contextMock = $this->createMock(SalesChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => null]),
            $contextMock
        );
    }
}
