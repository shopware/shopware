<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Exception\VerificationHashNotConfiguredException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class VerificationHashController extends AbstractController
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @internal
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @Since("6.3.4.1")
     * @Route("/sw-domain-hash.html", name="api.verification-hash.load", methods={"GET"}, defaults={"auth_required"=false})
     */
    public function load(): Response
    {
        $verificationHash = $this->systemConfigService->getString('core.store.verificationHash');
        if ($verificationHash === '') {
            throw new VerificationHashNotConfiguredException();
        }

        return new Response(
            $verificationHash,
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }
}
