<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationUpdateResult;
use Shopware\Core\System\Snippet\Request\InstallTranslationRequest;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationRemover;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('discovery')]
class TranslationController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TranslationConfig $config,
        private readonly TranslationMetadataStore $metadataStore,
        private readonly TranslationUpdater $translationUpdater,
        private readonly TranslationRemover $translationRemover,
    ) {
    }

    #[Route(
        path: '/api/_action/translation/list',
        name: 'api.action.translation.list',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:translation:read']],
        methods: ['GET'],
    )]
    public function list(): Response
    {
        $installed = $this->metadataStore->getLocalMetadata();

        $items = [];
        foreach ($this->config->languages as $language) {
            $entry = $installed->get($language->locale);

            $items[] = [
                'locale' => $language->locale,
                'name' => $language->name,
                'lastUpdate' => $entry?->updatedAt->format(\DateTimeInterface::ATOM),
                'progress' => $entry?->progress,
            ];
        }

        return new JsonResponse([
            'total' => \count($items),
            'items' => $items,
        ]);
    }

    #[Route(
        path: '/api/_action/translation/install',
        name: 'api.action.translation.install',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:translation:create']],
        methods: ['POST'],
    )]
    public function install(
        #[MapRequestPayload]
        InstallTranslationRequest $parameters,
        Context $context,
    ): Response {
        if ($parameters->all) {
            $locales = $this->config->locales;
        } else {
            $locales = $parameters->locales;
            $this->config->assertLocalesAreConfigured($locales);
        }

        $metadata = $this->metadataStore->getUpdatedLocalMetadata($locales);
        $result = $this->translationUpdater->update($metadata, $context, $parameters->activate);

        return $this->translationResponse($result, $this->unavailableLocales($locales, $metadata));
    }

    #[Route(
        path: '/api/_action/translation/update',
        name: 'api.action.translation.update',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:translation:update']],
        methods: ['POST'],
    )]
    public function update(Context $context): Response
    {
        // Refreshes every installed locale, so there are no requested-but-unavailable locales to report.
        return $this->translationResponse($this->translationUpdater->updateInstalled($context), []);
    }

    #[Route(
        path: '/api/_action/translation/{locale}',
        name: 'api.action.translation.delete',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:translation:delete']],
        methods: ['DELETE'],
    )]
    public function delete(string $locale): Response
    {
        $this->config->assertLocalesAreConfigured([$locale]);

        $this->translationRemover->remove($locale);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param list<string> $unavailable
     */
    private function translationResponse(TranslationUpdateResult $result, array $unavailable): Response
    {
        return new JsonResponse([
            'updated' => $result->updated,
            'skipped' => $result->skipped,
            'unavailable' => $unavailable,
        ]);
    }

    /**
     * Requested locales the remote translation source does not offer: they are configured (otherwise the
     * request would have been rejected earlier), but have no entry in the fetched metadata, so nothing
     * could be installed for them.
     *
     * @param list<string> $requestedLocales
     *
     * @return list<string>
     */
    private function unavailableLocales(array $requestedLocales, MetadataCollection $metadata): array
    {
        return array_values(array_diff($requestedLocales, $metadata->getKeys()));
    }
}
