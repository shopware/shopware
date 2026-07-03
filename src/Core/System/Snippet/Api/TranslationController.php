<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\Request\InstallTranslationRequest;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationRemover;
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
        private readonly AbstractTranslationLoader $translationLoader,
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

        return $this->loadTranslations(
            $this->metadataStore->getUpdatedLocalMetadata($locales),
            $context,
            $parameters->activate,
            $locales,
        );
    }

    #[Route(
        path: '/api/_action/translation/update',
        name: 'api.action.translation.update',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:translation:update']],
        methods: ['POST'],
    )]
    public function update(Context $context): Response
    {
        return $this->loadTranslations($this->metadataStore->getUpdatedLocalMetadata(), $context, true, []);
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
     * @param list<string> $requestedLocales locales the caller asked for, used to report requested locales that have no translation available
     */
    private function loadTranslations(MetadataCollection $metadata, Context $context, bool $activate, array $requestedLocales): Response
    {
        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();
        $skipped = array_values(array_diff($metadata->getKeys(), $localesRequiringUpdate));
        $unavailable = array_values(array_diff($requestedLocales, $metadata->getKeys()));

        foreach ($localesRequiringUpdate as $locale) {
            $this->translationLoader->load($locale, $context, $activate);
        }

        $this->metadataStore->save($metadata);

        return new JsonResponse([
            'updated' => $localesRequiringUpdate,
            'skipped' => $skipped,
            'unavailable' => $unavailable,
        ]);
    }
}
