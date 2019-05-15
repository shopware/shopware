[titleEn]: <>(Working with the Rest-API and an HTTP Client)
[metaDescriptionEn]: <>(One of the big advantages of working with the Shopware Platform is the Rest-API it comes with. Learn how to work with this API in a PHP context here)

Some things are not easy to solve without an API. For these cases Shopware has a REST-API!

## Overview

Shopware comes with a powerful REST-API. You can use this API by using a HTTP-Client, like curl or similar.

## Warning

For reasons of simplicity we will write a Shopware plugin, but in most cases this is not a good use case. Please do not
call the Shopware API through a plugin unless you do have a really good reason to do so!

## The HTTP Client

Lets start with the HTTP client. Since [GuzzleHTTP](http://docs.guzzlephp.org) is already in `shopware/core` required,
we can use it here. But there is one problem with `guzzle`. It does not have its own Symfony service implementation, so 
we have to fix it first.

```php
<?php declare(strict_types=1);

namespace Swag\RestApiHandling\Service;

use GuzzleHttp\Client;

class GuzzleClientService extends Client
{
}
```

Now we can register this class in our `services.xml` to pass `guzzle` by dependency injection.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\RestApiHandling\Service\GuzzleClientService" />
    </services>
</container>
```

Now we have prepared everything. It's time to use `guzzle` as entry point for our code.

## API Entrypoint

Let's create a class that includes our `guzzle` service by dependency injection, so that we can add multiple helpful 
functions to this class and simplify the use of the API.

```php
<?php declare(strict_types=1);

namespace Swag\RestApiHandling\Service;

use GuzzleHttp\Client;

class RestService
{
    /**
     * @var Client
     */
    private $restClient;

    public function __construct(Client $restClient)
    {
        $this->restClient = $restClient;
    }
}
```

`<plugin root>/src/Service/RestService.php`

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\RestApiHandling\Service\GuzzleClientService" />

        <service id="Swag\RestApiHandling\Service\RestService">
            <argument type="service" id="Swag\RestApiHandling\Service\GuzzleClientService"/>
        </service>
    </services>
</container>
```

`<plugin root>/src/Resources/config/services.xml`

##OAuth Token

The API is fully secured via [OAuth authentication](https://de.wikipedia.org/wiki/OAuth). So we need some helpers to get
the authentication token before we do any further requests. Therefore, we extend our `RestService` class to do this
during the constructor.

```php
<?php declare(strict_types=1);

namespace Swag\RestApiHandling\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class RestService
{
    /**
     * @var Client
     */
    private $restClient;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $authToken;

    public function __construct(Client $restClient, SystemConfigService $config)
    {
        $this->restClient = $restClient;
        $this->config = $config;
        $this->authToken = $this->getAuthToken();
    }
    
    private function getAuthToken(): string
        {
            $body = \json_encode([
                        'client_id' => 'administration',
                        'grant_type' => 'password',
                        'scopes' => $this->config->get('RestApiHandling.config.scope'),
                        'username' => $this->config->get('RestApiHandling.config.username'),
                        'password' => $this->config->get('RestApiHandling.config.password')
                    ]);
    
            $request = new Request(
                'POST',
                getenv('APP_URL') . '/api/oauth/token',
                ['Content-Type' => 'application/json'],
                $body
            );
    
            $response = $this->restClient->send($request);
    
            $body = json_decode($response->getBody()->getContents(), true);
    
            return $body['access_token'];
        }
}
```

Note our extension of the constructor by two properties! First we get the `SystemConfigService` to ask for information
we can maintain in the `administration`. Second we use the property `$authToken` to persist our authorization key at 
runtime.

Now we need to add a `config.xml` to our plugin so that we can maintain the required data information in the
administration.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/System/SystemConfig/Schema/config.xsd">

    <card>
        <title>User Data</title>
        <title lang="de-DE">Benutzerdaten</title>
        <input-field>
            <name>username</name>
            <label>Api user name</label>
            <label lang="de-DE">API Benutzername</label>
        </input-field>
        <input-field type="password">
            <name>password</name>
            <label>Api user password</label>
            <label lang="de-DE">API Benutzerpasswort</label>
        </input-field>
        <input-field type="single-select">
            <name>scope</name>
            <label>API access level</label>
            <label lang="de-DE">API Zugriffslevel</label>
            <options>
                <option>
                    <id>write</id>
                    <name>Write-Access</name>
                    <name lang="de-DE">Schreibzugriff</name>
                </option>
                <option>
                    <id>read</id>
                    <name>Read-Access</name>
                    <name lang="de-DE">Lesezugriff</name>
                </option>
            </options>
        </input-field>
    </card>
</config>
```

For further information about the `config.xml`, see the
[config.xml documentation](./../2-internals/4-plugins/070-plugin-config.md).

## Make API Calls easy

Last but not least, we currently unable to make authorized API calls. Let's fix this.

```php
<?php 

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RestService
{
    ...
    
    public function makeRequest(string $method, string $uri, ?array $body = null): ResponseInterface
    {
        $bodyEncoded = json_encode($body);

        $request = $this->createShopwareApiRequest($method, $uri, $bodyEncoded);

        $response = $this->restClient->send($request);

        return $response;
    }
    
    private function createShopwareApiRequest(string $method, string $uri, ?string $body = null): RequestInterface
    {
        return new Request(
            $method,
            getenv('APP_URL') . '/api/v1/' . $uri,
            [
                'Authorization' => 'Bearer ' . $this->authToken,
                'Accept' => '*/*'
            ],
            $body
        );
    }
}
```

The `makeRequest` function makes it easy to make API requests. All you have to do is call the `makeRequest` as follows:

```php
$this->restService->makeRequest('GET', 'product');
```

The first parameter is the HTTP method to be used.

The second parameter is the route / entity name you want to call. Some examples would be `product`, `rule` or `language`.

The third parameter is the data you might want to send to the API. These data can be written as an `array` and is
encoded by PHP according to the JSON scheme.

If you want to know more about how to work with the API, we recommend you take a look at the
[API documentation](./../3-api/__categoryInfo.md)

## Source

There is a GitHub repository available, containing an example plugin.
Check it out [here](https://github.com/shopware/swag-docs-rest-api-handling)
