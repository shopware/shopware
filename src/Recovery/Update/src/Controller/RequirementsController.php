<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Recovery\Common\DependencyInjection\Container;
use Shopware\Recovery\Common\Utils as CommonUtils;
use Shopware\Recovery\Update\Utils;
use Slim\App;

class RequirementsController
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var Slim
     */
    private $app;

    public function __construct(Container $container, App $app)
    {
        $this->container = $container;
        $this->app = $app;
    }

    public function checkRequirements(ServerRequestInterface $request, ResponseInterface $response)
    {
        $checks = include \dirname(__DIR__, 3) . '/Common/requirements.php';
        $paths = $checks['paths'];

        clearstatcache();
        $systemCheckPathResults = Utils::checkPaths($paths, SW_PATH);

        foreach ($systemCheckPathResults as $value) {
            if (!$value['result']) {
                $fileName = SW_PATH . '/' . $value['name'];
                if (!mkdir($fileName, 0777, true) && !is_dir($fileName)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $fileName));
                }
                @chmod($fileName, 0777);
            }
        }

        clearstatcache();
        $systemCheckPathResults = Utils::checkPaths($paths, SW_PATH);

        $hasErrors = false;
        foreach ($systemCheckPathResults as $value) {
            if (!$value['result']) {
                $hasErrors = true;
            }
        }

        $directoriesToDelete = [
            'engine/Library/Mpdf/tmp' => false,
            'engine/Library/Mpdf/ttfontdata' => false,
        ];

        CommonUtils::clearOpcodeCache();

        $results = [];
        foreach ($directoriesToDelete as $directory => $deleteDirecory) {
            $result = true;
            $filePath = SW_PATH . '/' . $directory;

            Utils::deleteDir($filePath, $deleteDirecory);
            if ($deleteDirecory && is_dir($filePath)) {
                $result = false;
                $hasErrors = true;
            }

            if ($deleteDirecory) {
                $results[$directory] = $result;
            }
        }

        $postParameters = $request->getParsedBody();

        if (!$hasErrors && $postParameters['force'] !== '1') {
            // No errors, skip page except if force parameter is set

            return $response->withRedirect($this->container->get('router')->pathFor('dbmigration'));
        }

        return $this->container->get('renderer')->render($response, 'checks.php', [
            'systemCheckResultsWritePermissions' => $systemCheckPathResults,
            'filesToDelete' => $results,
            'error' => $hasErrors,
        ]);
    }
}
