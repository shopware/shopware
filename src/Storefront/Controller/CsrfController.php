<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Storefront\Framework\Csrf\CsrfModes;
use Shopware\Storefront\Framework\Csrf\Exception\CsrfNotEnabledException;
use Shopware\Storefront\Framework\Csrf\Exception\CsrfWrongModeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - class will be removed as the csrf system will be removed in favor for the samesite approach
 */
class CsrfController extends StorefrontController
{
    /**
     * @var CsrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var bool
     */
    private $csrfEnabled;

    /**
     * @var string
     */
    private $csrfMode;

    /**
     * @internal
     */
    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, bool $csrfEnabled, string $csrfMode)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
        $this->csrfMode = $csrfMode;
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/csrf/generate", name="frontend.csrf.generateToken", defaults={"csrf_protected"=false, "XmlHttpRequest"=true}, methods={"POST"})
     */
    public function generateCsrf(Request $request): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        if (!$this->csrfEnabled) {
            throw new CsrfNotEnabledException();
        }

        if ($this->csrfMode !== CsrfModes::MODE_AJAX) {
            throw new CsrfWrongModeException(CsrfModes::MODE_AJAX);
        }

        $intent = (string) $request->request->get('intent', 'ajax');

        $token = $this->csrfTokenManager->getToken($intent);

        return new JsonResponse(['token' => $token->getValue()]);
    }
}
