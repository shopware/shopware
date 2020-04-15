<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client\Adapter;

use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;

class GoogleShoppingContentAccountResource
{
    /**
     * @var \Google_Service_ShoppingContent_Resource_Accounts
     */
    private $resource;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    public function __construct(\Google_Service_ShoppingContent_Resource_Accounts $resource, GoogleShoppingClient $googleShoppingClient)
    {
        $this->resource = $resource;
        $this->googleShoppingClient = $googleShoppingClient;
    }

    public function get(string $merchantId, string $accountId): array
    {
        return (array) $this->resource->get($merchantId, $accountId)->toSimpleObject();
    }

    /**
     * Fetching all merchant account of connected account, including sub-accounts.
     */
    public function list(): array
    {
        $accounts = (array) $this->resource->authinfo()->getAccountIdentifiers();
        $requests = [];

        $responses = empty($accounts) ? [] : $this->googleShoppingClient->deferExecute(function () use ($accounts, &$requests) {
            foreach ($accounts as $account) {
                if ($merchantId = $account->getMerchantId()) {
                    $requests[] = $this->resource->get($merchantId, $merchantId);
                } elseif ($accountId = $account->getAggregatorId()) {
                    $requests[] = $this->resource->listAccounts($accountId);
                }
            }

            $response = $this->googleShoppingClient->asyncRequests($requests);

            return $response['responses'];
        });

        $subAccounts = [];

        foreach ($responses as $index => $account) {
            if (array_key_exists('resources', $account)) {
                $subAccounts = array_merge($subAccounts, $account['resources']);
                unset($responses[$index]);
            }
        }

        return array_merge($responses, $subAccounts);
    }
}
