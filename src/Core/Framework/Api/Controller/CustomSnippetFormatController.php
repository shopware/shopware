<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @internal
 *
 * @Route(defaults={"_routeScope"={"api"}})
 */
class CustomSnippetFormatController
{
    private KernelPluginCollection $plugins;

    private Environment $twig;

    /**
     * @internal
     */
    public function __construct(KernelPluginCollection $plugins, Environment $twig)
    {
        $this->plugins = $plugins;
        $this->twig = $twig;
    }

    /**
     * @Since("6.4.18.0")
     * @Route("/api/_action/custom-snippet", name="api.action.custom-snippet", methods={"GET"})
     */
    public function snippets(): JsonResponse
    {
        $coreSnippets = $this->getCoreSnippets();
        $pluginSnippets = $this->getPluginSnippets();
        // NEXT-24122 - Allow app to define address formatting snippet

        return new JsonResponse([
            'data' => array_values(array_unique(array_merge($coreSnippets, $pluginSnippets))),
        ]);
    }

    /**
     * @Since("6.4.18.0")
     * @Route("/api/_action/custom-snippet/render", name="api.action.custom-snippet.render", methods={"POST"})
     */
    public function render(Request $request): JsonResponse
    {
        $format = $request->get('format') ?? [];
        $data = $request->get('data') ?? [];
        $parameters = array_merge_recursive(['format' => $format], $data);

        return new JsonResponse([
            'rendered' => $this->twig->render('@Framework/snippets/render.html.twig', $parameters),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function getCoreSnippets(): array
    {
        $directory = __DIR__ . '/../../Resources/views/snippets/';

        return $this->getSnippetsFromDir($directory);
    }

    /**
     * @return array<int, string>
     */
    private function getPluginSnippets(): array
    {
        $snippets = [];

        foreach ($this->plugins->getActives() as $plugin) {
            $snippetDir = $plugin->getPath() . '/Resources/views/snippets/';

            if (!is_dir($snippetDir)) {
                continue;
            }

            $snippets = array_merge($snippets, $this->getSnippetsFromDir($snippetDir));
        }

        return $snippets;
    }

    /**
     * @return array<int, string>
     */
    private function getSnippetsFromDir(string $directory): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($directory)
            ->name('*.html.twig')
            ->sortByName()
            ->notName('render.html.twig')
            ->ignoreUnreadableDirs();

        $snippets = array_values(array_map(static function (\SplFileInfo $file) use ($directory): string {
            return ltrim(mb_substr(str_replace('.html.twig', '', $file->getPathname()), mb_strlen($directory)), '/');
        }, iterator_to_array($finder)));

        return $snippets;
    }
}
