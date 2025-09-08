<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use GuzzleHttp\Client;
use Shopware\Core\Content\Product\Extension\ResolveListingAggregationsExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @title Example how you control the listing product aggregations
 *
 * @description This example shows how you can control the listing product aggregations. It allows you to resolve the listing aggregations by your own over an API call or an own storage
 */
#[AsEventListener(
    event: 'listing-loader.resolve-listing-aggregations.pre',
    method: '__invoke'
)]
readonly class ResolveListingAggregationsExample
{
    /**
     * @param EntityRepository<ProductCollection> $repository
     */
    public function __construct(
        // you can inject your own services
        private Client $client,
        private EntityRepository $repository
    ) {
    }

    public function __invoke(ResolveListingAggregationsExtension $event): void
    {
        $criteria = $event->criteria;

        // building a json aware array for the API call
        $context = [
            'salesChannelId' => $event->context->getSalesChannelId(),
            'currencyId' => $event->context->getCurrency(),
            'languageId' => $event->context->getLanguageId(),
        ];

        // do an api call against your own server or another storage, or whatever you want
        $ids = $this->client->get('https://your-api.com/listing-ids', [
            'query' => [
                'criteria' => json_encode($criteria),
                'context' => json_encode($context),
            ],
        ]);

        $data = json_decode($ids->getBody()->getContents(), true);

        // create the expected result
        $criteria = new Criteria($data['ids']);

        $event->result = $this->repository->aggregate($criteria, $event->context->getContext());

        // stop the event propagation, so core function will not be executed
        $event->stopPropagation();
    }
}
