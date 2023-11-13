<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
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
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class CaptchaController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBasicCaptchaPageletLoader $basicCaptchaPageletLoader,
        private readonly AbstractCaptcha $basicCaptcha
    ) {
    }

    #[Route(path: '/basic-captcha', name: 'frontend.captcha.basic-captcha.load', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
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

    #[Route(path: '/basic-captcha-validate', name: 'frontend.captcha.basic-captcha.validate', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $response = [];
        $formId = $request->get('formId');
        if (!$formId) {
            throw RoutingException::missingRequestParameter('formId');
        }

        if ($this->basicCaptcha->isValid($request, [])) {
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
