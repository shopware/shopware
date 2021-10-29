<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script\Registry;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Script\AppScriptEntity;
use Shopware\Core\Framework\App\Script\ExecutableScript;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Twig\Cache\FilesystemCache;

/**
 * @internal only for use by the app-system
 */
class ExecutableDatabaseScriptLoader implements ExecutableScriptLoaderInterface
{
    private EntityRepositoryInterface $scriptRepository;

    private string $cacheDir;

    public function __construct(EntityRepositoryInterface $scriptRepository, string $cacheDir)
    {
        $this->scriptRepository = $scriptRepository;
        $this->cacheDir = $cacheDir . '/twig/scripts';
    }

    public function loadExecutableScripts(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true))
            ->addAssociation('app');

        $scripts = $this->scriptRepository->search($criteria, $context);

        $executableScripts = [];
        /** @var AppScriptEntity $script */
        foreach ($scripts as $script) {
            /** @var AppEntity $app */
            $app = $script->getApp();
            /** @var \DateTimeInterface $lastModified */
            $lastModified = $script->getUpdatedAt() ?? $script->getCreatedAt();
            $executableScripts[$script->getHook()][] = new ExecutableScript(
                $script->getName(),
                $script->getScript(),
                $lastModified,
                [
                    'cache' => new FilesystemCache($this->cacheDir . '/' . md5($app->getName() . $app->getVersion())),
                ]
            );
        }

        return $executableScripts;
    }
}
