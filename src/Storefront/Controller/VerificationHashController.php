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
 * @RouteScope(scopes={"api"})
 */
class VerificationHashController extends AbstractController
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @Since("6.3.4.1")
     * @Route("/sw-domain-hash.html", name="api.verification-hash.load", methods={"GET"}, defaults={"auth_required"=false})
     *
     * @throws VerificationHashNotConfiguredException
     */
    public function load(): Response
    {
        $verificationHash = $this->systemConfigService->get('core.store.verificationHash');

        if (empty($verificationHash)) {
            throw new VerificationHashNotConfiguredException();
        }

        return Response::create(
            $verificationHash,
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }
}
