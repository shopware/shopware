<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentDataProviderCollector
{
    /**
     * @var array<string, list<AbstractDocumentDataProvider>>
     */
    private array $providersMap = [];

    /**
     * @param iterable<AbstractDocumentDataProvider> $providers
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        iterable $providers,
        private readonly EntityRepository $orderRepository,
    ) {
        foreach ($providers as $provider) {
            foreach ($provider->getDocumentTypes() as $docType) {
                $this->providersMap[$docType][] = $provider;
            }
        }
    }

    public function collectFor(string $docType, string $orderId, string $orderVersionId, Context $context, ?string $docNumber = null): RenderInput
    {
        $providers = $this->providersMap[$docType] ?? [];

        $orderCriteria = new Criteria([$orderId]);
        // todo: use order version passed in
        foreach ($providers as $provider) {
            $provider->enrichOrderCriteria($orderCriteria);
        }

        $order = $this->orderRepository->search($orderCriteria, $context)->first();
        if (!$order instanceof OrderEntity) {
            // todo: error handling
            throw new \RuntimeException('Order not found');
        }

        if ($docNumber === null) {
            $docNumber = '1001'; // todo: generate actual doc number
        }

        $renderInput = new RenderInput(
            $docType,
            $docNumber,
            $order,
        );
        foreach ($providers as $provider) {
            $renderInput->setInput(
                $provider->getKey(),
                $provider->provideRenderingData($order),
            );
        }

        return $renderInput;
    }
}
