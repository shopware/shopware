<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\ActionButton\ActionButtonLoader;
use Shopware\Core\Framework\App\ActionButton\AppActionLoader;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\Manifest\ModuleLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
 * @RouteScope(scopes={"api"})
 */
class AppActionController extends AbstractController
{
    /**
     * @var ActionButtonLoader
     */
    private $actionButtonLoader;

    /**
     * @var Executor
     */
    private $executor;

    /**
     * @var AppActionLoader
     */
    private $appActionFactory;

    /**
     * @var ModuleLoader
     */
    private $moduleLoader;

    public function __construct(
        ActionButtonLoader $actionButtonLoader,
        AppActionLoader $appActionFactory,
        Executor $executor,
        ModuleLoader $moduleLoader
    ) {
        $this->actionButtonLoader = $actionButtonLoader;
        $this->executor = $executor;
        $this->appActionFactory = $appActionFactory;
        $this->moduleLoader = $moduleLoader;
    }

    /**
     * @Since("6.3.3.0")
     * @Route("api/app-system/action-button/{entity}/{view}", name="api.app_system.action_buttons", methods={"GET"})
     */
    public function getActionsPerView(string $entity, string $view, Context $context): Response
    {
        return new JsonResponse([
            'actions' => $this->actionButtonLoader->loadActionButtonsForView($entity, $view, $context),
        ]);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("api/app-system/action-button/run/{id}", name="api.app_system.action_button.run", methods={"POST"})
     */
    public function runAction(string $id, Request $request, Context $context): Response
    {
        $entityIds = $request->get('ids', []);

        $action = $this->appActionFactory->loadAppAction($id, $entityIds, $context);

        $this->executor->execute($action, $context);

        return new JsonResponse();
    }

    /**
     * @Since("6.3.3.0")
     * @Route("api/app-system/modules", name="api.app_system.modules", methods={"GET"})
     */
    public function getModules(Context $context): Response
    {
        return new JsonResponse(['modules' => $this->moduleLoader->loadModules($context)]);
    }
}
