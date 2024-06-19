<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use GuzzleHttp\ClientInterface;
use Shopware\Core\Content\Product\Extension\ResolveListingExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class ResolveListingExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'listing-loader.resolve-listing-ids.pre' => 'replace',
        ];
    }

    /**
     * @param EntityRepository<ProductCollection> $repository
     */
    public function __construct(
        // you can inject your own services
        private ClientInterface $client,
        private EntityRepository $repository
    ) {
    }

    public function replace(ResolveListingExtension $event): void
    {
        $criteria = $event->criteria;

        // building a json aware array for the API call
        $context = [
            'salesChannelId' => $event->context->getSalesChannelId(),
            'currencyId' => $event->context->getCurrency(),
            'languageId' => $event->context->getLanguageId(),
        ];

        // do an api call against your own server or another storage, or whatever you want
        $ids = $this->client->request('GET', 'https://your-api.com/listing-ids', [
            'query' => [
                'criteria' => json_encode($criteria),
                'context' => json_encode($context),
            ],
        ]);

        $data = json_decode($ids->getBody()->getContents(), true);

        $criteria = new Criteria($data['ids']);

        $event->result = $this->repository->search($criteria, $event->context->getContext());

        // stop the event propagation, so the core function will not be executed
        $event->stopPropagation();
    }
}
