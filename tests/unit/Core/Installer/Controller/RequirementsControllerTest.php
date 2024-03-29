<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\RequirementsController;
use Shopware\Core\Installer\Requirements\RequirementsValidatorInterface;
use Shopware\Core\Installer\Requirements\Struct\PathCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(RequirementsController::class)]
class RequirementsControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    public function testRequirementsRouteValidatesAndRendersChecks(): void
    {
        $request = new Request();
        $request->setMethod('GET');

        $checks = new RequirementsCheckCollection([new PathCheck('check', RequirementCheck::STATUS_SUCCESS)]);

        $validator = $this->createMock(RequirementsValidatorInterface::class);
        $validator->expects(static::once())
            ->method('validateRequirements')
            ->with(static::isInstanceOf(RequirementsCheckCollection::class))
            ->willReturn($checks);

        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/requirements.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'requirementChecks' => $checks,
                ])
            )
            ->willReturn('checks');

        $jwtCertificateGenerator = $this->createMock(JwtCertificateGenerator::class);
        $jwtCertificateGenerator->expects(static::never())->method('generate');

        $controller = new RequirementsController([$validator], $jwtCertificateGenerator, __DIR__);
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->requirements($request);
        static::assertSame('checks', $response->getContent());
    }

    public function testRequirementsRouteRedirectsOnPostWhenChecksPass(): void
    {
        $request = new Request();
        $request->setMethod('POST');

        $checks = new RequirementsCheckCollection([new PathCheck('check', RequirementCheck::STATUS_SUCCESS)]);

        $validator = $this->createMock(RequirementsValidatorInterface::class);
        $validator->expects(static::once())
            ->method('validateRequirements')
            ->with(static::isInstanceOf(RequirementsCheckCollection::class))
            ->willReturn($checks);

        $twig = $this->createMock(Environment::class);
        $twig->expects(static::never())->method('render');

        $router = $this->createMock(RouterInterface::class);
        $router->expects(static::once())->method('generate')
            ->with('installer.license', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/license');

        $jwtCertificateGenerator = $this->createMock(JwtCertificateGenerator::class);
        $jwtCertificateGenerator->expects(static::once())->method('generate')
            ->with(__DIR__ . '/config/jwt/private.pem', __DIR__ . '/config/jwt/public.pem');

        $controller = new RequirementsController([$validator], $jwtCertificateGenerator, __DIR__);
        $controller->setContainer($this->getInstallerContainer($twig, ['router' => $router]));

        $response = $controller->requirements($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/license', $response->getTargetUrl());
    }

    public function testRequirementsRouteDoesNotRedirectIfValidationFails(): void
    {
        $request = new Request();
        $request->setMethod('POST');

        $checks = new RequirementsCheckCollection([new PathCheck('check', RequirementCheck::STATUS_ERROR)]);

        $validator = $this->createMock(RequirementsValidatorInterface::class);
        $validator->expects(static::once())
            ->method('validateRequirements')
            ->with(static::isInstanceOf(RequirementsCheckCollection::class))
            ->willReturn($checks);

        $twig = $this->createMock(Environment::class);
        $twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/requirements.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'requirementChecks' => $checks,
                ])
            )
            ->willReturn('checks');

        $jwtCertificateGenerator = $this->createMock(JwtCertificateGenerator::class);
        $jwtCertificateGenerator->expects(static::never())->method('generate');

        $controller = new RequirementsController([$validator], $jwtCertificateGenerator, __DIR__);
        $controller->setContainer($this->getInstallerContainer($twig));

        $response = $controller->requirements($request);
        static::assertSame('checks', $response->getContent());
    }
}
