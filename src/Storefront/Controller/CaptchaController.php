<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Captcha\Annotation\Captcha;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Pagelet\Captcha\AbstractBasicCaptchaPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 *
 * @internal (flag:FEATURE_NEXT_12455)
 */
class CaptchaController extends StorefrontController
{
    private AbstractBasicCaptchaPageletLoader $basicCaptchaPageletLoader;

    private SessionInterface $session;

    public function __construct(
        AbstractBasicCaptchaPageletLoader $basicCaptchaPageletLoader,
        SessionInterface $session
    ) {
        $this->basicCaptchaPageletLoader = $basicCaptchaPageletLoader;
        $this->session = $session;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/basic-captcha", name="frontend.captcha.basic-captcha.load", methods={"GET"}, defaults={"auth_required"=false}, defaults={"XmlHttpRequest"=true})
     */
    public function loadBasicCaptcha(Request $request, SalesChannelContext $context): Response
    {
        $formId = $request->get('formId');
        $page = $this->basicCaptchaPageletLoader->load($request, $context);
        $this->session->set($formId . BasicCaptcha::BASIC_CAPTCHA_SESSION, $page->getCaptcha()->getCode());

        return $this->renderStorefront('@Storefront/storefront/component/captcha/basicCaptchaImage.html.twig', [
            'page' => $page,
            'formId' => $formId,
        ]);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/basic-captcha-validate", name="frontend.captcha.basic-captcha.validate", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     * @Captcha
     */
    public function validate(Request $request): JsonResponse
    {
        $formId = $request->get('formId');
        $fakeSession = (string) time();
        $this->session->set($formId . BasicCaptcha::BASIC_CAPTCHA_SESSION, $fakeSession);

        return new JsonResponse(['session' => $fakeSession]);
    }
}
