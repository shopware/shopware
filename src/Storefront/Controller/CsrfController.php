<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

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
 * @RouteScope(scopes={"storefront"})
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
