<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Storybook\StorybookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
#[Package('framework')]
class StorybookController extends AbstractController
{
    private const BASE_TEMPLATE = <<<TWIG
        {% set assets = theme_config('assets.css') %}
        {% for file in assets %}
            <link rel="stylesheet"
                href="{{ asset(file, 'theme') }}">
        {% endfor %}
        {{ component(componentName, componentProps) }}
    TWIG;

    public function __construct(
        private readonly Environment $twig,
        private readonly StorybookService $storybookService,
    ) {
    }

    /**
     * @phpstan-ignore shopware.routeScope (Not a real Storefront controller, only used in dev envs)
     */
    #[Route(
        path: '/storybook/{component}',
        name: 'storybook.component',
        env: 'dev',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET],
    )]
    public function storybook(string $component, Request $request): Response
    {
        $storybookDomain = (string) EnvironmentHelper::getVariable('STORYBOOK_DOMAIN', 'http://localhost:6006');

        if ($request->headers->get('Origin') !== $storybookDomain) {
            throw new NotFoundHttpException();
        }

        $salesChannelContext = $this->storybookService->createSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $themeId = $this->storybookService->getThemeId($salesChannelId);

        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);

        try {
            $componentProps = $this->storybookService->resolveComponentProps($request, $salesChannelContext);

            $content = $this->twig->render(
                $this->twig->createTemplate(self::BASE_TEMPLATE),
                [
                    'componentName' => $component,
                    'componentProps' => $componentProps,
                    'context' => $salesChannelContext,
                    'themeId' => $themeId,
                ]
            );

            $response = new Response($content);
        } catch (RuntimeError|SyntaxError $e) {
            $response = new Response(
                '<div style="color: red; padding: 20px;">Template error: ' . htmlspecialchars($e->getMessage()) . '</div>',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $response->headers->set('Access-Control-Allow-Origin', $storybookDomain);

        return $response;
    }
}
