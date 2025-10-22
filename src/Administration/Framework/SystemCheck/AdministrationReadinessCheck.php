<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\SystemCheck;

use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Bundle as ShopwareBundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class AdministrationReadinessCheck extends BaseCheck
{
    public const NAME = 'AdministrationReadiness';

    /**
     * @internal
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly KernelInterface $kernel,
        private readonly ViteFileAccessorDecorator $viteFileAccessorDecorator,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function category(): Category
    {
        return Category::FEATURE;
    }

    public function run(): Result
    {
        // check index route
        $indexRoute = $this->router->generate('administration.index');
        $indexRequest = Request::create($indexRoute);
        $indexRequestStart = microtime(true);
        $indexResponse = $this->kernel->handle($indexRequest);
        $indexResponseTime = microtime(true) - $indexRequestStart;

        // Looks for JS modules injected in the body
        $indexContent = \is_string($indexResponse->getContent()) ? $indexResponse->getContent() : '';
        $indexPageJsBundlesFound = preg_match_all('/type="module" src="(.+?)"/', $indexContent, $matches);
        $indexPageJsBundles = $matches[1];

        // check js build artifacts entrypoints
        $missingJsBundles = $this->checkForMissingAdministrationBundles();

        $status = Status::FAILURE;
        if (
            $indexResponse->getStatusCode() < Response::HTTP_BAD_REQUEST
            && $indexPageJsBundlesFound >= 1
            && \count($missingJsBundles) === 0
        ) {
            $status = Status::OK;
        }

        return new Result(
            $this->name(),
            $status,
            $status === Status::OK ? 'Admininstration is OK' : 'Administration is unhealthy',
            $status === Status::OK,
            [
                'indexResponseTime' => $indexResponseTime,
                'indexPageJsBundlesFound' => $indexPageJsBundlesFound,
                'indexPageJsBundles' => $indexPageJsBundles,
                'missingArtifactsForJsBundles' => $missingJsBundles,
            ]
        );
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    /**
     * @return array<string>
     */
    private function checkForMissingAdministrationBundles(): array
    {
        $missingJsBundles = [];
        foreach ($this->kernel->getBundles() as $bundle) {
            // plain symfony bundles don't bring JS assets
            if (!$bundle instanceof ShopwareBundle) {
                continue;
            }

            // check if ShopwareBundle contains an administration js package
            $administrationPackageExists = $this->filesystem->exists($bundle->getPath() . '/Resources/app/administration/package.json');

            // check vite bundle data, which is also returned by http request GET /config
            // gather any (admin) entrypoints it contains
            // if the js package isn't build properly there shouldn't be any entrypoints here
            $bundleData = $this->viteFileAccessorDecorator->getBundleData($bundle);
            $entrypoints = $bundleData['entryPoints'] ?? [];

            if ($administrationPackageExists && \count($entrypoints) === 0) {
                $missingJsBundles[] = $bundle->getName();
            }
        }

        return $missingJsBundles;
    }
}
