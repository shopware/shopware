<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\System\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 * @Acl({"system.plugin_maintain"})
 */
class ExtensionStoreDataController extends AbstractController
{
    private AbstractExtensionDataProvider $extensionDataProvider;

    private EntityRepositoryInterface $languageRepository;

    private EntityRepositoryInterface $userRepository;

    public function __construct(
        AbstractExtensionDataProvider $extensionListingProvider,
        EntityRepositoryInterface $userRepository,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->extensionDataProvider = $extensionListingProvider;
        $this->languageRepository = $languageRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/installed", name="api.extension.installed", methods={"GET"})
     */
    public function getInstalledExtensions(Context $context): Response
    {
        $context = $this->switchContext($context);

        return new JsonResponse(
            $this->extensionDataProvider->getInstalledExtensions($context)
        );
    }

    private function switchContext(Context $context): Context
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return $context;
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return $context;
        }

        $criteria = new Criteria([$source->getUserId()]);

        /** @var UserEntity|null $user */
        $user = $this->userRepository->search($criteria, $context)->first();

        if ($user === null) {
            return $context;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('localeId', $user->getLocaleId()));
        $criteria->setLimit(1);
        $languageId = $this->languageRepository->searchIds($criteria, $context)->firstId();

        if ($languageId === null) {
            return $context;
        }

        return new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$languageId, Defaults::LANGUAGE_SYSTEM]
        );
    }
}
