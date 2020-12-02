<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class MediaFileFetchRedirectTestController extends AbstractController
{
    /**
     * @Since("6.3.4.1")
     * @Route("/api/v{version}/_action/redirect-to-echo", name="api.action.test.redirect-to-echo", defaults={"auth_required"=false}, methods={"GET"})
     */
    public function redirectAction(Request $request, string $version): RedirectResponse
    {
        $parameters = array_merge($request->query->all(), [
            'version' => $version,
        ]);
        $response = new RedirectResponse($this->generateUrl('api.action.test.echo_json', $parameters));
        // only send location header
        $response->setContent('');

        return $response;
    }

    /**
     * @Since("6.3.4.1")
     * @Route("/api/v{version}/_action/echo-json", name="api.action.test.echo_json", defaults={"auth_required"=false}, methods={"GET"})
     */
    public function echoJsonAction(Request $request): JsonResponse
    {
        $data = [
            'headers' => $request->headers->all(),
            'query' => $request->query->all(),
        ];

        return new JsonResponse($data);
    }
}
