<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\System\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['system.plugin_maintain']])]
#[Package('merchant-services')]
class ExtensionStoreDataController extends AbstractController
{
    public function __construct(
        private readonly AbstractExtensionDataProvider $extensionDataProvider,
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $languageRepository
    ) {
    }

    #[Route(path: '/api/_action/extension/installed', name: 'api.extension.installed', methods: ['GET'])]
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
