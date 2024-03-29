<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('core')]
class FinishController extends AbstractController
{
    #[Route('/finish', name: 'finish', defaults: ['step' => 3])]
    public function default(Request $request, #[Autowire('%kernel.cache_dir%')] string $cacheDir): Response
    {
        // @codeCoverageIgnoreStart
        if ($request->getMethod() === Request::METHOD_POST) {
            if ($request->hasSession()) {
                $request->getSession()->invalidate();
            }

            $self = $_SERVER['SCRIPT_FILENAME'];
            \assert(\is_string($self));

            $redirectUrl = $request->getBasePath() . '/admin';

            // Cleanup our generated cache dir in system temporary directory
            $fs = new Filesystem();
            $fs->remove($cacheDir);

            // Below this line call only php native functions as we deleted our own files already
            unlink($self);

            header('Content-Type: text/html; charset=utf-8');
            echo '<script>window.location.href = "' . $redirectUrl . '" </script>';
            exit;
        }
        // @codeCoverageIgnoreEnd

        return $this->render('finish.html.twig');
    }
}
