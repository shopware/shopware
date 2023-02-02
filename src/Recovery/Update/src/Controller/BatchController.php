<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Recovery\Common\Steps\FinishResult;
use Shopware\Recovery\Common\Steps\MigrationStep;
use Shopware\Recovery\Common\Steps\ResultMapper;
use Shopware\Recovery\Common\Steps\ValidResult;
use Shopware\Recovery\Common\Utils;
use Shopware\Recovery\Update\DependencyInjection\Container;
use Shopware\Recovery\Update\FilesystemFactory;
use Shopware\Recovery\Update\PathBuilder;
use Shopware\Recovery\Update\Steps\UnpackStep;

/**
 * @package system-settings
 */
class BatchController
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var ResultMapper
     */
    private $resultMapper;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->resultMapper = new ResultMapper();
    }

    public function applyMigrations(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryParameters = $request->getQueryParams();

        $offset = (int) $queryParameters['offset'];
        $total = (int) $queryParameters['total'];
        $modus = $queryParameters['modus'];

        /** @var MigrationCollectionLoader $migrationCollectionLoader */
        $migrationCollectionLoader = $this->container->get('migration.collection.loader');

        $shopwareVersion = (string) $this->container->get('shopware.version');
        $versionSelectionMode = $modus === MigrationStep::UPDATE_DESTRUCTIVE
            // only execute safe destructive migrations
            ? MigrationCollectionLoader::VERSION_SELECTION_SAFE
            : MigrationCollectionLoader::VERSION_SELECTION_ALL;

        $coreMigrations = $migrationCollectionLoader->collectAllForVersion(
            $shopwareVersion,
            // only execute safe destructive migrations
            $versionSelectionMode
        );

        $result = (new MigrationStep($coreMigrations))
            ->run($modus, $offset, $total);

        return $this->toJson($response, 200, $this->resultMapper->toExtJs($result));
    }

    /**
     * @throws \RuntimeException
     */
    public function unpack(ServerRequestInterface $request, ResponseInterface $response)
    {
        // Manual updates do not contain files to overwrite
        if (UPDATE_IS_MANUAL) {
            Utils::clearOpcodeCache();

            return $this->toJson($response, 200, $this->resultMapper->toExtJs(new FinishResult(0, 0)));
        }

        $queryParameters = $request->getQueryParams();

        $offset = (int) $queryParameters['offset'];
        $total = (int) $queryParameters['total'];

        /** @var FilesystemFactory $factory */
        $factory = $this->container->get('filesystem.factory');

        $localFilesystem = $factory->createLocalFilesystem();
        $remoteFilesystem = $factory->createRemoteFilesystem();

        /** @var PathBuilder $pathBuilder */
        $pathBuilder = $this->container->get('path.builder');

        $debug = false;
        $step = new UnpackStep($localFilesystem, $remoteFilesystem, $pathBuilder, $debug);

        $result = $step->run($offset, $total);

        if ($result instanceof ValidResult) {
            Utils::clearOpcodeCache();
        }

        return $this->toJson($response, 200, $this->resultMapper->toExtJs($result));
    }

    private function toJson(ResponseInterface $response, int $code, array $data): ResponseInterface
    {
        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode($data, \JSON_PRETTY_PRINT));
    }
}
