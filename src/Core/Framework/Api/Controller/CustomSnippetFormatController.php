<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Event\CustomSnippetCollectedEvent;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippet;
use Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippetCollection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class CustomSnippetFormatController
{
    private const DEFAULT_SYMBOLS = ['-', ',', '~'];

    /**
     * @var iterable<string, BundleInterface>
     */
    private iterable $bundles;

    private Environment $twig;

    private EventDispatcherInterface $dispatcher;

    /**
     * @param iterable<string, BundleInterface> $bundles
     *
     * @internal
     */
    public function __construct(
        iterable $bundles,
        Environment $twig,
        EventDispatcherInterface $dispatcher
    ) {
        $this->bundles = $bundles;
        $this->twig = $twig;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @Since("6.14.0.0")
     * @Route("/api/_action/custom-snippet", name="api.action.custom-snippet", methods={"GET"})
     */
    public function snippets(): JsonResponse
    {
        $snippets = new CustomSnippetCollection();

        $this->addPlainSnippets($snippets);
        $this->addCoreSnippets($snippets);
        $this->addPluginSnippets($snippets);
        // TODO: Add app snippets

        $this->dispatcher->dispatch(new CustomSnippetCollectedEvent($snippets));

        return new JsonResponse([
            'data' => $snippets->toArray(),
        ]);
    }

    /**
     * @Since("6.14.0.0")
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

    private function addCoreSnippets(CustomSnippetCollection $snippets): void
    {
        $directory = __DIR__ . '/../../Resources/views/snippets/';

        $this->collectSnippetsFromDir($snippets, $directory);
    }

    private function addPluginSnippets(CustomSnippetCollection $snippets): void
    {
        foreach ($this->bundles as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $snippetDir = $bundle->getPath() . '/Resources/views/snippets/';

            if (!is_dir($snippetDir)) {
                continue;
            }

            $this->collectSnippetsFromDir($snippets, $snippetDir);
        }
    }

    private function addPlainSnippets(CustomSnippetCollection $snippets): void
    {
        foreach (self::DEFAULT_SYMBOLS as $symbol) {
            $snippets->set(bin2hex($symbol), CustomSnippet::createPlain($symbol));
        }
    }

    private function collectSnippetsFromDir(CustomSnippetCollection $snippets, string $directory): void
    {
        $finder = new Finder();
        $finder->files()
            ->in($directory)
            ->name('*.html.twig')
            ->notName('plain.html.twig')
            ->notName('render.html.twig')
            ->ignoreUnreadableDirs();

        $fileNames = array_values(array_map(static function (\SplFileInfo $file) use ($directory): string {
            return ltrim(mb_substr(str_replace('.html.twig', '', $file->getPathname()), mb_strlen($directory)), '/');
        }, iterator_to_array($finder)));

        foreach ($fileNames as $fileName) {
            $snippet = CustomSnippet::createSnippet($fileName);

            $snippets->set($fileName, $snippet);
        }
    }
}
