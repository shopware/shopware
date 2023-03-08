<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\PhpBinaryFinder;
use App\Services\RecoveryManager;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @internal
 */
#[Package('core')]
class PhpConfigController extends AbstractController
{
    public function __construct(
        private readonly PhpBinaryFinder $binaryFinder,
        private readonly RecoveryManager $recoveryManager
    ) {
    }

    #[Route('/configure', name: 'configure', defaults: ['step' => 1])]
    public function index(Request $request): Response
    {
        try {
            $shopwareLocation = $this->recoveryManager->getShopwareLocation();
        } catch (\RuntimeException) {
            $shopwareLocation = null;
        }

        if ($phpBinary = $request->request->get('phpBinary')) {
            // Reset the latest version to force a new check
            $request->getSession()->remove('latestVersion');

            $request->getSession()->set('phpBinary', $phpBinary);

            $channel = $request->request->getAlpha('channel', 'stable');
            $request->getSession()->set('channel', $channel);

            return $this->redirectToRoute($shopwareLocation === null ? 'install' : 'update');
        }

        return $this->render('php_config.html.twig', [
            'phpBinary' => $request->getSession()->get('phpBinary', $this->binaryFinder->find()),
            'channel' => $request->getSession()->get('channel', 'stable'),
            'shopwareLocation' => $shopwareLocation,
        ]);
    }
}
