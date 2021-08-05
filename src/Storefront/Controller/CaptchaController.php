<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Pagelet\Captcha\AbstractBasicCaptchaPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CaptchaController extends StorefrontController
{
    private AbstractBasicCaptchaPageletLoader $basicCaptchaPageletLoader;

    private AbstractCaptcha $basicCaptcha;

    public function __construct(
        AbstractBasicCaptchaPageletLoader $basicCaptchaPageletLoader,
        AbstractCaptcha $basicCaptcha
    ) {
        $this->basicCaptchaPageletLoader = $basicCaptchaPageletLoader;
        $this->basicCaptcha = $basicCaptcha;
    }

    /**
     * @Since("6.4.2.0")
     * @Route("/basic-captcha", name="frontend.captcha.basic-captcha.load", methods={"GET"}, defaults={"auth_required"=false}, defaults={"XmlHttpRequest"=true})
     */
    public function loadBasicCaptcha(Request $request, SalesChannelContext $context): Response
    {
        $formId = $request->get('formId');
        $page = $this->basicCaptchaPageletLoader->load($request, $context);
        $request->getSession()->set($formId . BasicCaptcha::BASIC_CAPTCHA_SESSION, $page->getCaptcha()->getCode());

        return $this->renderStorefront('@Storefront/storefront/component/captcha/basicCaptchaImage.html.twig', [
            'page' => $page,
            'formId' => $formId,
        ]);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/basic-captcha-validate", name="frontend.captcha.basic-captcha.validate", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function validate(Request $request): JsonResponse
    {
        $formId = $request->get('formId');
        if (!$formId) {
            throw new MissingRequestParameterException('formId');
        }

        if ($this->basicCaptcha->isValid($request)) {
            $fakeSession = $request->get(BasicCaptcha::CAPTCHA_REQUEST_PARAMETER);
            $request->getSession()->set($formId . BasicCaptcha::BASIC_CAPTCHA_SESSION, $fakeSession);

            return new JsonResponse(['session' => $fakeSession]);
        }

        $violations = $this->basicCaptcha->getViolations();
        $formViolations = new ConstraintViolationException($violations, []);
        $response[] = [
            'type' => 'danger',
            'error' => 'invalid_captcha',
            'input' => $this->renderView('@Storefront/storefront/component/captcha/basicCaptchaFields.html.twig', [
                'formId' => $request->get('formId'),
                'formViolations' => $formViolations,
            ]),
        ];

        return new JsonResponse($response);
    }
}
