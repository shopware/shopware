<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('after-sales')]
class DocumentGeneratorController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly DecoderInterface $serializer,
        private readonly DataValidator $dataValidator
    ) {
    }

    #[Route(
        path: '/api/_action/order/document/{documentTypeName}/create',
        name: 'api.action.document.bulk.create',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:create']],
        methods: [Request::METHOD_POST]
    )]
    public function createDocuments(Request $request, string $documentTypeName, Context $context): JsonResponse
    {
        $documents = $this->serializer->decode($request->getContent(), 'json');

        if (!\is_array($documents) || $documents === []) {
            throw DocumentException::invalidRequestParameter('Request parameters must be an array of documents object');
        }

        $operations = [];
        $definition = new DataValidationDefinition();

        $itemDefinition = (new DataValidationDefinition())
            ->add('orderId', new NotBlank(), new Type('string'))
            ->add('fileType', new Choice(choices: [PdfRenderer::FILE_EXTENSION]))
            ->add('static', new Type('bool'))
            ->add('referencedDocumentId', new Uuid());

        $configDefinition = (new DataValidationDefinition())
            ->add('documentNumber', new Type('string'))
            ->add('documentDate', new Type('string'));

        $itemDefinition->addSub('config', $configDefinition);
        $definition->addList('documents', $itemDefinition);

        $this->dataValidator->validate(
            ['documents' => $documents],
            $definition
        );

        foreach ($documents as $operation) {
            $operations[(string) $operation['orderId']] = new DocumentGenerateOperation(
                $operation['orderId'],
                $operation['fileType'] ?? PdfRenderer::FILE_EXTENSION,
                $operation['config'] ?? [],
                $operation['referencedDocumentId'] ?? null,
                $operation['static'] ?? false
            );
        }

        return new JsonResponse($this->documentGenerator->generate($documentTypeName, $operations, $context));
    }

    #[Route(
        path: '/api/_action/document/{documentId}/upload',
        name: 'api.action.document.upload',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['document:update']],
        methods: [Request::METHOD_POST]
    )]
    public function uploadToDocument(Request $request, string $documentId, Context $context): JsonResponse
    {
        $documentIdStruct = $this->documentGenerator->upload(
            $documentId,
            $context,
            $request
        );

        return new JsonResponse(
            [
                'documentId' => $documentIdStruct->getId(),
                'documentMediaId' => $documentIdStruct->getMediaId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
                'documentA11yMediaId' => $documentIdStruct->getA11yMediaId(),
            ]
        );
    }
}
