<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Exception\VerificationHashNotConfiguredException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('storefront')]
class VerificationHashController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    #[Route(path: '/sw-domain-hash.html', name: 'api.verification-hash.load', defaults: ['auth_required' => false], methods: ['GET'])]
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
