<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('storefront')]
class CaptchaController extends AbstractController
{
    /**
     * @internal
     *
     * @param AbstractCaptcha[] $captchas
     */
    public function __construct(private readonly iterable $captchas)
    {
    }

    #[Route(path: '/api/_action/captcha_list', name: 'api.action.captcha.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $ids = [];

        foreach ($this->captchas as $captcha) {
            $ids[] = $captcha->getName();
        }

        return new JsonResponse($ids);
    }
}
