<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client\Adapter;

use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Symfony\Component\HttpFoundation\Request;

class GoogleShoppingContentAccountResource
{
    /**
     * @var \Google_Service_ShoppingContent_Resource_Accounts
     */
    private $accountResource;

    /**
     * @var \Google_Service_ShoppingContent_Resource_Accountstatuses
     */
    private $accountStatusResource;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    public function __construct(
        \Google_Service_ShoppingContent_Resource_Accounts $accountResource,
        \Google_Service_ShoppingContent_Resource_Accountstatuses $accountStatusResource,
        GoogleShoppingClient $googleShoppingClient
    ) {
        $this->accountResource = $accountResource;
        $this->accountStatusResource = $accountStatusResource;
        $this->googleShoppingClient = $googleShoppingClient;
    }

    public function get(string $merchantId, string $accountId): array
    {
        return (array) $this->accountResource->get($merchantId, $accountId)->toSimpleObject();
    }

    public function getStatus(string $merchantId, string $accountId): array
    {
        return (array) $this->accountStatusResource->get($merchantId, $accountId)->toSimpleObject();
    }

    public function claimWebsite(string $merchantId, string $accountId, bool $overwrite = false): array
    {
        return (array) $this->accountResource->claimwebsite($merchantId, $accountId, [
            'overwrite' => $overwrite,
        ])->toSimpleObject();
    }

    /**
     * Fetching all merchant account of connected account, including sub-accounts.
     */
    public function list(): array
    {
        $accounts = (array) $this->accountResource->authinfo()->getAccountIdentifiers();
        $requests = [];

        $responses = empty($accounts) ? [] : $this->googleShoppingClient->deferExecute(function () use ($accounts, &$requests) {
            foreach ($accounts as $account) {
                if ($merchantId = $account->getMerchantId()) {
                    $requests[] = $this->accountResource->get($merchantId, $merchantId);
                } elseif ($accountId = $account->getAggregatorId()) {
                    $requests[] = $this->accountResource->listAccounts($accountId);
                }
            }

            $response = $this->googleShoppingClient->asyncRequests($requests, true, count($requests));

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

    public function updateWebsiteUrl(string $merchantId, string $accountId, string $websiteUrl): array
    {
        $account = $this->accountResource->get($merchantId, $accountId);
        $updateAccount = new \Google_Service_ShoppingContent_Account();

        $updateAccount->setWebsiteUrl($websiteUrl);
        $updateAccount->setName($account->getName());
        $updateAccount->setId($merchantId);

        $updateWebsiteRequest = $this->accountResource->update($merchantId, $accountId, $updateAccount);

        return (array) $updateWebsiteRequest->toSimpleObject();
    }

    public function update(Request $request, string $merchantId, string $accountId): array
    {
        $account = $this->accountResource->get($merchantId, $accountId);
        $account->setName($request->get('name'));
        $account->setWebsiteUrl($request->get('websiteUrl'));
        $account->setAdultContent($request->get('adultContent', false));
        $this->setBusinessInformationCountry($account, $request->get('country'));

        return (array) $this->accountResource->update($merchantId, $accountId, $account)->toSimpleObject();
    }

    private function setBusinessInformationCountry(\Google_Service_ShoppingContent_Account $account, string $country): void
    {
        $businessInformation = $account->getBusinessInformation();

        if (empty($businessInformation)) {
            $businessInformation = new \Google_Service_ShoppingContent_AccountBusinessInformation();
        }

        $address = $businessInformation->getAddress();

        if (empty($address)) {
            $address = new \Google_Service_ShoppingContent_AccountAddress();
        }

        $address->setCountry($country);
    }
}
