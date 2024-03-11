<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use GuzzleHttp\Exception\TransferException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\LicenseController;
use Shopware\Core\Installer\License\LicenseFetcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(LicenseController::class)]
class LicenseControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    public function testLicenseRouteRendersLicenseOnGet(): void
    {
        $request = new Request();
        $request->setMethod('GET');

        $licenseFetcher = $this->createMock(LicenseFetcher::class);
        $licenseFetcher->expects(static::once())
            ->method('fetch')
            ->with($request)
            ->willReturn('licenseText');

        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/license.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'licenseAgreement' => 'licenseText',
                    'error' => null,
                ]),
            )
            ->willReturn('license');

        $controller = new LicenseController($licenseFetcher);
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->license($request);
        static::assertSame('license', $response->getContent());
    }

    public function testLicenseRouteRendersErrorIfLicenseCanNotBeFetched(): void
    {
        $request = new Request();
        $request->setMethod('GET');

        $licenseFetcher = $this->createMock(LicenseFetcher::class);
        $licenseFetcher->expects(static::once())
            ->method('fetch')
            ->with($request)
            ->willThrowException(new TransferException('license can not be fetched.'));

        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/license.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'licenseAgreement' => null,
                    'error' => 'license can not be fetched.',
                ])
            )
            ->willReturn('license');

        $controller = new LicenseController($licenseFetcher);
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->license($request);
        static::assertSame('license', $response->getContent());
    }

    public function testLicenseRouteRendersLicenseIfTosNotAcceptedOnPost(): void
    {
        $request = new Request();
        $request->setMethod('POST');

        $licenseFetcher = $this->createMock(LicenseFetcher::class);
        $licenseFetcher->expects(static::once())
            ->method('fetch')
            ->with($request)
            ->willReturn('licenseText');

        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/license.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'licenseAgreement' => 'licenseText',
                    'error' => null,
                ])
            )
            ->willReturn('license');

        $controller = new LicenseController($licenseFetcher);
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->license($request);
        static::assertSame('license', $response->getContent());
    }

    public function testLicenseRouteRedirectsToDatabaseConfigOnPostWithAcceptedTos(): void
    {
        $request = new Request([], ['tos' => true]);
        $request->setMethod('POST');

        $licenseFetcher = $this->createMock(LicenseFetcher::class);
        $licenseFetcher->expects(static::never())
            ->method('fetch');

        $twig = $this->createMock(Environment::class);
        $twig->expects(static::never())->method('render');

        $router = $this->createMock(RouterInterface::class);
        $router->expects(static::once())->method('generate')
            ->with('installer.database-configuration', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/database-configuration');

        $controller = new LicenseController($licenseFetcher);
        $controller->setContainer($this->getInstallerContainer($twig, ['router' => $router]));

        $response = $controller->license($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/database-configuration', $response->getTargetUrl());
    }
}
