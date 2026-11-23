<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Storybook\StorybookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\TwigComponent\ComponentFactory;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
#[Package('discovery')]
class StorybookController extends AbstractController
{
    private const THEME_STYLESHEETS_TEMPLATE = <<<TWIG
        {% set assets = theme_config('assets.css') %}
        {% for file in assets %}
            <link rel="stylesheet"
                href="{{ asset(file, 'theme') }}">
        {% endfor %}
    TWIG;

    private const BASE_TEMPLATE = self::THEME_STYLESHEETS_TEMPLATE . "\n{{ component(componentName, componentProps) }}";

    public function __construct(
        private readonly Environment $twig,
        private readonly ComponentFactory $componentFactory,
        private readonly StorybookService $storybookService,
        private readonly HtmlSanitizer $htmlSanitizer,
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
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function storybook(string $component, Request $request): Response
    {
        // WHY: The component name is inserted into a Twig tag; allow a leading ASCII letter followed only by
        // ASCII letters, digits, colons, underscores, or hyphens to prevent Twig syntax injection.
        if (!preg_match('/^[A-Za-z][A-Za-z0-9:_-]*$/D', $component)) {
            throw new NotFoundHttpException();
        }

        $storybookDomain = (string) EnvironmentHelper::getVariable('STORYBOOK_DOMAIN', 'http://localhost:6006');

        if ($request->headers->get('Origin') !== $storybookDomain) {
            throw new NotFoundHttpException();
        }

        $storybookParameters = null;
        $storybookBlocks = [];
        if ($request->isMethod(Request::METHOD_POST)) {
            $payload = json_decode($request->getContent(), true, flags: \JSON_BIGINT_AS_STRING);

            if (!\is_array($payload)) {
                $payload = [];
            }

            $storybookParameters = $payload['props'] ?? null;
            $storybookBlocks = $payload['blocks'] ?? null;

            $invalidBlock = false;
            if (\is_array($storybookBlocks)) {
                foreach ($storybookBlocks as $blockName => $blockContent) {
                    // WHY: Block names enter generated Twig tags, so accept ASCII identifiers only to prevent Twig syntax injection.
                    if (!\is_string($blockName) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $blockName) || !\is_string($blockContent)) {
                        $invalidBlock = true;
                        break;
                    }
                }
            }

            if (!\is_array($storybookParameters) || !\is_array($storybookBlocks) || $invalidBlock) {
                return $this->createErrorResponse(
                    'The Storybook preview payload must contain string block content and property data.',
                    Response::HTTP_BAD_REQUEST,
                    $storybookDomain,
                );
            }
        }

        try {
            $componentTemplate = $this->componentFactory->metadataFor($component)->getTemplate();
        } catch (\InvalidArgumentException) {
            return $this->createErrorResponse(
                \sprintf('Unknown Storybook component "%s".', $component),
                Response::HTTP_BAD_REQUEST,
                $storybookDomain,
            );
        }

        $salesChannelContext = $this->storybookService->createSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $themeId = $this->storybookService->getThemeId($salesChannelId);

        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);

        try {
            $componentProps = $this->storybookService->resolveComponentProps($request, $salesChannelContext, $storybookParameters);

            if ($storybookBlocks !== []) {
                $declaredBlocks = $this->twig->load($componentTemplate)->getBlockNames();
                $unknownBlocks = array_diff(array_keys($storybookBlocks), $declaredBlocks);

                if ($unknownBlocks !== []) {
                    return $this->createErrorResponse(
                        \sprintf('Unknown block name(s) for component "%s": %s.', $component, implode(', ', $unknownBlocks)),
                        Response::HTTP_BAD_REQUEST,
                        $storybookDomain,
                    );
                }
            }

            $template = self::BASE_TEMPLATE;
            $renderContext = [
                'componentName' => $component,
                'componentProps' => $componentProps,
                'context' => $salesChannelContext,
                'themeId' => $themeId,
            ];

            if ($storybookBlocks !== []) {
                $propAttributes = '';

                foreach (array_keys($componentProps) as $prop) {
                    $propAttributes .= \sprintf(' :%s="componentProps.%s"', $prop, $prop);
                }

                $blockOverrides = '';
                foreach ($storybookBlocks as $blockName => $blockContent) {
                    $blockOverrides .= '<twig:block name="' . $blockName . '">{{ blockContent.' . $blockName . '|raw }}</twig:block>';
                    $renderContext['blockContent'][$blockName] = $this->htmlSanitizer->sanitize($blockContent, field: 'storybook');
                }

                $template = self::THEME_STYLESHEETS_TEMPLATE . "\n<twig:" . $component . $propAttributes . '>' . $blockOverrides . '</twig:' . $component . '>';
            }

            $content = $this->twig->render($this->twig->createTemplate($template), $renderContext);
            $response = new Response($content);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
            $response = $this->createErrorResponse(
                'Template error: ' . htmlspecialchars($e->getMessage()),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $storybookDomain,
            );
        }

        $response->headers->set('Access-Control-Allow-Origin', $storybookDomain);

        return $response;
    }

    private function createErrorResponse(string $message, int $statusCode, string $storybookDomain): Response
    {
        $response = new Response(
            $message,
            $statusCode,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
        $response->headers->set('Access-Control-Allow-Origin', $storybookDomain);

        return $response;
    }
}
