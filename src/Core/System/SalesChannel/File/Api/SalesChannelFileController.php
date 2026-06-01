<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileSourceLoader;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class SalesChannelFileController extends AbstractController
{
    private const USER_PROVIDED_CONTENT_BLOCK = 'user_provided_content';

    public function __construct(
        private readonly SalesChannelFileDiscovery $discovery,
        private readonly SalesChannelFileConfigurationLoader $configurationLoader,
        private readonly SalesChannelFileLoader $loader,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly SalesChannelFileRequestPathResolver $requestPathResolver,
        private readonly Environment $twig,
        private readonly SalesChannelFileSourceLoader $sourceLoader,
    ) {
    }

    #[Route(path: '/api/_action/sales-channel-file/{fileFamily}/{salesChannelId}', name: 'api.action.sales_channel_file.list', methods: ['GET'])]
    public function list(string $fileFamily, string $salesChannelId, Context $context): JsonResponse
    {
        $this->requestPathResolver->validateFileFamily($fileFamily);

        $configurations = $this->configurationLoader->loadForFileFamily($fileFamily, $salesChannelId, $context);
        $files = [];

        foreach ($this->discovery->discover($fileFamily) as $file) {
            $configuration = $configurations[$file->fileName] ?? null;

            $files[] = [
                'fileFamily' => $file->fileFamily,
                'fileName' => $file->fileName,
                'templatePath' => $file->templatePath,
                'contentType' => $file->contentType,
                'templates' => $this->serializeTemplates($file->templates, $context),
                'supportsUserProvidedContent' => $this->supportsUserProvidedContent($file->templates),
                'configuration' => $configuration === null ? null : $this->serializeConfiguration($configuration),
            ];
        }

        return new JsonResponse(['data' => $files]);
    }

    #[Route(path: '/api/_action/sales-channel-file/{fileFamily}/{salesChannelId}/preview', name: 'api.action.sales_channel_file.preview', methods: ['POST'])]
    public function preview(string $fileFamily, string $salesChannelId, RequestDataBag $dataBag): JsonResponse
    {
        $fileName = $dataBag->get('fileName');
        if (!\is_string($fileName)) {
            throw SalesChannelException::missingSalesChannelFileName();
        }

        $templatePath = $this->requestPathResolver->buildTemplatePath($fileFamily, $fileName);

        $templateOverrides = $dataBag->get('templateOverrides') ?? [];
        if ($templateOverrides instanceof RequestDataBag) {
            $templateOverrides = $templateOverrides->all();
        }

        if (!\is_array($templateOverrides)) {
            throw SalesChannelException::invalidSalesChannelFileTemplateOverrides();
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);
        $result = $this->loader->preview($templatePath, $salesChannelContext, $templateOverrides);

        if ($result === null) {
            throw SalesChannelException::salesChannelFileNotFound($fileFamily, $fileName);
        }

        return new JsonResponse([
            'fileName' => $result->fileName,
            'contentType' => $result->contentType,
            'content' => $result->content,
        ]);
    }

    /**
     * @return array{id: string, enabled: bool, templateOverrides: array<string, string>}
     */
    private function serializeConfiguration(SalesChannelFileEntity $configuration): array
    {
        return [
            'id' => $configuration->getId(),
            'enabled' => $configuration->isEnabled(),
            'templateOverrides' => $configuration->getTemplateOverrides(),
        ];
    }

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     *
     * @return list<array{twigNamespace: string, templateName: string, templateContent: string, sourceName: string, sourceType: string, sourceIcon: string|null, role: string}>
     */
    private function serializeTemplates(array $templates, Context $context): array
    {
        $serialized = [];
        $baseTwigNamespace = array_key_last($templates);
        $sources = $this->sourceLoader->load(array_keys($templates), $context);

        foreach ($templates as $twigNamespace => $templateName) {
            $source = $sources[$twigNamespace];

            $serialized[] = [
                'twigNamespace' => $twigNamespace,
                'templateName' => $templateName,
                'templateContent' => $this->loadTemplateContent($templateName),
                'sourceName' => $source['sourceName'],
                'sourceType' => $source['sourceType'],
                'sourceIcon' => $source['sourceIcon'],
                'role' => $twigNamespace === $baseTwigNamespace ? 'base' : 'extension',
            ];
        }

        return $serialized;
    }

    private function loadTemplateContent(string $templateName): string
    {
        try {
            return $this->twig->getLoader()->getSourceContext($templateName)->getCode();
        } catch (LoaderError) {
            return '';
        }
    }

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     */
    private function supportsUserProvidedContent(array $templates): bool
    {
        foreach ($templates as $templateName) {
            $source = $this->loadTemplateContent($templateName);

            if (preg_match('/{%-?\s*block\s+' . self::USER_PROVIDED_CONTENT_BLOCK . '\b/', $source) === 1) {
                return true;
            }
        }

        return false;
    }
}
