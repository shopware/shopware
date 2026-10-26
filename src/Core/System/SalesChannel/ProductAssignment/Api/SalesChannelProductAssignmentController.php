<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\ProductAssignment\Api;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssigner;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SalesChannelProductAssignmentController extends AbstractController
{
    public const MAX_PRODUCT_IDS = 1000;

    /**
     * Every product group and category is resolved against the whole catalog,
     * so these lists stay far shorter than the explicit product ids.
     */
    public const MAX_PRODUCT_STREAM_IDS = 25;

    public const MAX_CATEGORY_IDS = 50;

    public function __construct(
        private readonly SalesChannelProductAssigner $assigner,
        private readonly DataValidator $validator,
    ) {
    }

    #[Route(path: '/api/_action/sales-channel/{salesChannelId}/product-assignment', name: 'api.action.sales_channel.product_assignment', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product_visibility:create', 'product:read']], methods: ['POST'])]
    public function assign(string $salesChannelId, RequestDataBag $dataBag, Context $context): JsonResponse
    {
        $data = $dataBag->all();
        $this->validator->validate($data, $this->buildValidationDefinition());

        $productStreamIds = $this->idList($data, 'productStreamIds');
        $categoryIds = $this->idList($data, 'categoryIds');
        $this->ensureSourcePrivileges($productStreamIds, $categoryIds, $context);

        $result = $this->assigner->assign(
            $salesChannelId,
            $this->idList($data, 'productIds'),
            $productStreamIds,
            $categoryIds,
            (int) ($data['visibility'] ?? ProductVisibilityDefinition::VISIBILITY_ALL),
            $context,
        );

        return new JsonResponse(['assigned' => $result->assigned, 'complete' => $result->complete]);
    }

    /**
     * Resolving a product group or category reveals and publishes the products behind it,
     * so the caller must be allowed to read the source it names.
     *
     * @param list<string> $productStreamIds
     * @param list<string> $categoryIds
     */
    private function ensureSourcePrivileges(array $productStreamIds, array $categoryIds, Context $context): void
    {
        $missing = [];

        if ($productStreamIds !== [] && !$context->isAllowed('product_stream:read')) {
            $missing[] = 'product_stream:read';
        }

        if ($categoryIds !== [] && !$context->isAllowed('category:read')) {
            $missing[] = 'category:read';
        }

        if ($missing !== []) {
            throw SalesChannelException::missingPrivileges($missing);
        }
    }

    private function buildValidationDefinition(): DataValidationDefinition
    {
        return (new DataValidationDefinition('sales_channel.product_assignment'))
            ->add('productIds', ...$this->idListConstraints(self::MAX_PRODUCT_IDS))
            ->add('productStreamIds', ...$this->idListConstraints(self::MAX_PRODUCT_STREAM_IDS))
            ->add('categoryIds', ...$this->idListConstraints(self::MAX_CATEGORY_IDS))
            ->add('visibility', new Type('int'), new Choice(choices: [
                ProductVisibilityDefinition::VISIBILITY_LINK,
                ProductVisibilityDefinition::VISIBILITY_SEARCH,
                ProductVisibilityDefinition::VISIBILITY_ALL,
            ]));
    }

    /**
     * @param positive-int $max
     *
     * @return list<Constraint>
     */
    private function idListConstraints(int $max): array
    {
        return [new Type('array'), new Count(max: $max), new All([new Uuid()])];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function idList(array $data, string $key): array
    {
        $ids = $data[$key] ?? [];
        \assert(\is_array($ids));

        return array_values(array_unique(array_map('strval', $ids)));
    }
}
