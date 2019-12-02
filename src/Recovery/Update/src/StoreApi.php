<?php declare(strict_types=1);

namespace Shopware\Recovery\Update;

use Shopware\Recovery\Common\HttpClient\Client;

class StoreApi
{
    /**
     * @var Client
     */
    private $client;

    /*
     * @var string
     */
    private $baseUrl;

    /**
     * @param string $baseUrl
     */
    public function __construct(Client $client, $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param string[] $names
     * @param int      $version
     *
     * @return array
     */
    public function getProductsByNamesAndVersion(array $names, $version)
    {
        if (empty($names)) {
            return [];
        }

        $requestPayload = [
            'criterion' => [
                'version' => [
                    $version,
                ],
                'pluginName' => $names,
            ],
        ];

        return $this->doRequest($requestPayload);
    }

    /**
     * @param string[] $names
     *
     * @return array
     */
    public function getProductsByNames(array $names)
    {
        if (empty($names)) {
            return [];
        }

        $requestPayload = [
            'criterion' => [
                'pluginName' => $names,
            ],
        ];

        return $this->doRequest($requestPayload);
    }

    /**
     * @param array $requestPayload
     *
     * @return array
     */
    private function doRequest($requestPayload)
    {
        $queryParams = [
            'method' => 'call',
            'arg0' => 'GET',
            'arg1' => 'product',
            'arg2' => json_encode($requestPayload),
        ];

        $queryParams = http_build_query($queryParams, null, '&');

        $url = $this->baseUrl . '?' . $queryParams;

        $response = $this->client->post($url);

        $result = simplexml_load_string($response->getBody());
        $result = $result->call;

        if ($result->status === 'failed') {
            throw new \RuntimeException($result->response->message);
        }

        $result = $result->response->_search_result;
        $result = json_decode($result);
        $result = json_decode($result->_products, true);

        return $result;
    }
}
