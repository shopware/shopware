[titleEn]: <>(Working with the Rest-API and an HTTP Client)
[metaDescriptionEn]: <>(One of the big advantages of working with Shopware 6 is the Rest-API it comes with. Learn how to work with this API in a PHP context here)
[hash]: <>(article:how_to_api_http_client)

Some things are not easy to solve without an API. For these cases Shopware 6 has a REST-API!

## Overview

Shopware 6 comes with a powerful REST-API. You can use this API by using a HTTP-Client, like curl or similar.

## Warning

For reasons of simplicity we wrote a Shopware 6 plugin, but in most cases this is not a good use case. Please do not
call the Shopware 6 API through a plugin unless you do have a really good reason to do so!

## API Entrypoint

Let's create a class that makes use of [Guzzle](http://docs.guzzlephp.org), which is already included in the
`shopware/core`, so that we can add multiple helpful functions to this class and simplify the use of the API.

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

    public function __construct()
    {
        $this->restClient = new Client();
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
        <service id="Swag\RestApiHandling\Service\RestService" />
    </services>
</container>
```

`<plugin root>/src/Resources/config/services.xml`

## OAuth Token

The Admin API is secured via [OAuth authentication](https://de.wikipedia.org/wiki/OAuth), so we need some helpers to get
the authentication token before we do any further requests. Therefore, we extend our `RestService` class to do this
during the constructor.

```php
<?php declare(strict_types=1);

namespace Swag\RestApiHandling\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class RestService
{
    /**
     * @var Client
     */
    private $restClient;

    /**
     * @var SystemConfigService
     */
    private $config;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var \DateTimeInterface
     */
    private $expiresAt;

    public function __construct(SystemConfigService $config)
    {
        $this->restClient = new Client();
        $this->config = $config;
    }
    
    private function getAdminAccess(): void
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

        $this->setAccessData($body);
    }
    
    private function setAccessData(array $body): void
    {
        $this->accessToken = $body['access_token'];
        $this->refreshToken = $body['refresh_token'];
        $this->expiresAt = $this->calculateExpiryTime((int) $body['expires_in']);
    }

    private function calculateExpiryTime(int $expiresIn): \DateTimeInterface
    {
        $expiryTimestamp = (new \DateTime())->getTimestamp() + $expiresIn;

        return (new \DateTimeImmutable())->setTimestamp($expiryTimestamp);
    }

    private function createShopwareApiRequest(string $method, string $uri, ?string $body = null): RequestInterface
    {
        return new Request(
            $method,
            getenv('APP_URL') . '/api/v3/' . $uri,
            [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => '*/*'
            ],
            $body
        );
    }
}
```

Note our extension of the constructor! First we get the `SystemConfigService` to ask for information we can maintain in
the `administration`.

Since we have changed our `RestService` constructor we need to change our `services.xml`.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\RestApiHandling\Service\RestService">
            <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
        </service>
    </services>
</container>
``` 

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

## Authorization problems

One of the problems that can occur when working with the Admin API is that your access token has expired. To 
avoid having to deal with this problem we have already included the refresh token and expiration time in our properties,
so let's start automatically generating a new access token.

```php
<?php

class RestService
{
    ...
    
    private function send(RequestInterface $request, string $uri)
    {
        if ($this->expiresAt <= (new \DateTime())) {
            $this->refreshAuthToken();

            $body = $request->getBody()->getContents();

            $request = $this->createShopwareApiRequest($request->getMethod(), $uri, $body);
        }

        return $this->restClient->send($request);
    }
    
    private function refreshAuthToken(): void
    {
        $body = \json_encode([
            'client_id' => 'administration',
            'grant_type' => 'refresh_token',
            'scopes' => $this->config->get('RestApiHandling.config.scope'),
            'refresh_token' => $this->refreshToken
        ]);

        $request = new Request(
            'POST',
            getenv('APP_URL') . '/api/oauth/token',
            ['Content-Type' => 'application/json'],
            $body
        );

        $response = $this->restClient->send($request);

        $body = json_decode($response->getBody()->getContents(), true);

        $this->setAccessData($body);
    }
}
```

## Make API Calls easy

Last but not least, we are currently unable to make authorized API calls. Let's fix this.

```php
<?php 

use Psr\Http\Message\ResponseInterface;

class RestService
{
    ...
    
    public function request(string $method, string $uri, ?array $body = null): ResponseInterface
    {
        if ($this->accessToken === null || $this->refreshToken === null || $this->expiresAt === null) {
            $this->getAdminAccess();
        }
        
        $bodyEncoded = json_encode($body);

        $request = $this->createShopwareApiRequest($method, $uri, $bodyEncoded);

        return $this->send($request, $uri);
    }
}
```

The `request` function makes it easy to send API requests. It requests the API credentials if they do not already exist,
and converts your request into a Admin API request. All you have to do is call the `request` function as follows:

```php
$this->restService->request('GET', 'product');
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
