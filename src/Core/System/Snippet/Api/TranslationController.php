<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationUpdateResult;
use Shopware\Core\System\Snippet\Request\InstallTranslationRequest;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationRemover;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
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
        $items = $this->metadataStore->getTranslationList();

        return new JsonResponse([
            'total' => \count($items),
            'items' => $items,
        ]);
    }

    #[Route(
        path: '/api/_action/translation/meta',
        name: 'api.action.translation.meta',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:translation:read']],
        methods: ['GET'],
    )]
    public function meta(): Response
    {
        return new JsonResponse([
            // Built-in languages are exactly the locales excluded from the community translation download
            'builtInLocales' => $this->config->excludedLocales,
            'communityTranslationsUrl' => $this->config->communityTranslationsUrl?->__toString(),
            'documentationUrlSnippetKey' => $this->config->documentationUrlSnippetKey,
            'completenessThreshold' => $this->config->completenessThreshold,
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
        $plan = $this->translationUpdater->planInstall($locales, $metadata);

        // Nothing could be installed for any requested locale, so fail instead of reporting a misleading success.
        if ($plan->nothingCanBeInstalled()) {
            throw SnippetException::translationsUnavailable($plan->unavailableLocales);
        }

        $result = $this->translationUpdater->install($plan, $context, $parameters->activate);

        if ($metadata->getLocalesRequiringUpdate() !== []) {
            $this->metadataStore->save($metadata);
        }

        return $this->translationResponse($result, $plan->unavailableLocales);
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
}
