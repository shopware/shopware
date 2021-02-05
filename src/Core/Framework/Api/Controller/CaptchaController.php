<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class CaptchaController extends AbstractController
{
    /**
     * @var iterable|AbstractCaptcha[]
     */
    private $captchas;

    public function __construct(iterable $captchas)
    {
        $this->captchas = $captchas;
    }

    /**
     * @Since("6.2.0.0")
     * Returns the IDs of all available captchas
     *
     * @Route("/api/_action/captcha_list", name="api.action.captcha.list", methods={"GET"})
     */
    public function list(): JsonResponse
    {
        $ids = [];

        foreach ($this->captchas as $captcha) {
            $ids[] = $captcha->getName();
        }

        return new JsonResponse($ids);
    }
}
