<?php declare(strict_types=1);

$connection = require __DIR__ . '/boo.php';
class Api
{
    private string $host;
    private ?string $token = null;

    public function __construct(array $data)
    {
        $this->host = $data['url'];
        $this->token = $this->getAccessToken($data['oauth']);
    }

    public function fetchAll(string $url, array $params = [])
    {
        $params['page'] = 1;
        $params['limit'] = 100;

        $response = $this->request($url, $params);

        $all = [];
        while (!empty($response['data'])) {
            foreach($response['data'] as $item) {
                unset($item['apiAlias']);
                if (count(array_keys($item)) === 1) {
                    $all[] = $item[array_keys($item)[0]];
                } else {
                    $all[] = $item;
                }
            }

            $params['page']++;
            $response = $this->request($url, $params);
        }

        return $all;
    }

    public function fetchRow(string $url, array $params = [])
    {
        $response = $this->request($url, $params);

        return $response['data'][0];
    }

    public function fetchOne(string $url, array $params = [])
    {
        $response = $this->request($url, $params);

        $item = $response['data'][0];
        unset($item['apiAlias']);

        return $item[array_keys($item)[0]];
    }

    private function getAccessToken(array $oauth)
    {
        $response = $this->request('/api/oauth/token', $oauth);

        return $response['access_token'];
    }

    private function request(string $url, array $params = [])
    {
        $resource = curl_init($this->host . $url);

        curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($resource, CURLOPT_POST, true);
        curl_setopt($resource, CURLOPT_POSTFIELDS, json_encode($params));

        $headers = ['Content-Type:application/json', 'Accept:application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($resource, CURLOPT_HTTPHEADER, $headers);

        return json_decode(curl_exec($resource), true);
    }
}

$data = json_decode(file_get_contents(__DIR__ . '/env.json'), true);
$api = new Api($data);

echo str_pad('Fetching listing urls...', 30);
$listings = $api->fetchAll('/api/search/seo-url', [
    'includes' => ['seo_url' => ['seoPathInfo']],
    'fields' => ['seoPathInfo'],
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.navigation.page'],
        ['type' => 'equals', 'field' => 'isDeleted', 'value' => false],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true],
    ],
]);

$time = microtime(true);
$listings = array_map(function(string $url) { return '/' . $url; }, $listings);
file_put_contents(__DIR__ . '/fixtures/listing_urls.json', json_encode($listings, JSON_PRETTY_PRINT));
echo ' collected: ' . count($listings) . ' urls' . PHP_EOL;

echo str_pad('Fetching product urls...', 30);
$productUrls = $api->fetchAll('/api/search/seo-url', [
    'includes' => ['seo_url' => ['seoPathInfo']],
    'fields' => ['seoPathInfo'],
    'filter' => [
        ['type' => 'equals', 'field' => 'routeName', 'value' => 'frontend.detail.page'],
        ['type' => 'equals', 'field' => 'isDeleted', 'value' => false],
        ['type' => 'equals', 'field' => 'isCanonical', 'value' => true],
    ],
]);
$productUrls = array_map(function(string $url) { return '/' . $url; }, $productUrls);
file_put_contents(__DIR__ . '/fixtures/product_urls.json', json_encode($productUrls, JSON_PRETTY_PRINT));
echo ' collected: ' . count($productUrls) . ' urls' . PHP_EOL;

echo str_pad('Fetching product numbers...', 30);
$products = $api->fetchAll('/api/search/product', [
    'includes' => ['product' => ['productNumber', 'id']],
    'fields' => ['productNumber', 'id'],
]);
file_put_contents(__DIR__ . '/fixtures/products.json', json_encode($products, JSON_PRETTY_PRINT));
echo ' collected: ' . count($products) . ' product numbers' . PHP_EOL;


echo str_pad('Fetching search dictionary...', 30);
$keywords = $api->fetchAll('/api/search/product-keyword-dictionary', [
    'includes' => ['product_keyword_dictionary' => ['keyword']],
    'fields' => ['keyword'],
    'filter' => [
        ['type' => 'equals', 'field' => 'languageId', 'value' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
    ],
]);
file_put_contents(__DIR__ . '/fixtures/keywords.json', json_encode($keywords, JSON_PRETTY_PRINT));
echo ' collected: ' . count($keywords) . ' keywords' . PHP_EOL;


$salesChannel = $api->fetchRow('/api/search/sales-channel', [
    'fields' => ['languageId', 'currencyId', 'countryId'],
    'includes' => [
        'sales_channel' => ['languageId', 'currencyId', 'countryId'],
    ],
    'filter' => [
        ['type' => 'equals', 'field' => 'typeId', 'value' => '8a243080f92e4c719546314b577cf82b'],
    ],
]);

$salesChannel['salutationId'] = 'ed643807c9f84cc8b50132ea3ccb1c3b';
file_put_contents(__DIR__ . '/fixtures/sales_channel.json', json_encode($salesChannel, JSON_PRETTY_PRINT));
