[titleEn]: <>(Extending the cookie consent manager)
[metaDescriptionEn]: <>(This HowTo will take a look at extending the cookie consent manager with custom cookies and reacting to your users consent.)
[hash]: <>(article:how_to_plugin_cookies)

## Extend the cookie consent manager
Adding custom cookies basically requires you to decorate a service, so make sure to have a look at the [guide for decorating services](./../4-how-to/080-decorating-a-service.md) first.

This page covers 3 parts:

1. [The first part of this guide provides basic examples to match the guide for service decorations mentioned above](#code-examples-to-follow-along-the-service-decoration-guide)
2. [The second part explains, how you can listen for cookie consent changes via JavaScript](#reacting-to-cookie-configuration-changes-via-javascript) 
3. [The third part takes a closer look at the cookie object itself](#cookie-object) 


### Code examples to follow along the *service decoration* guide
Make sure to update the namespaces to fit to your plugin.

**services.xml**

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
       <service id="PluginName\Framework\Cookie\CustomCookieProvider"
                decorates="Shopware\Storefront\Framework\Cookie\CookieProviderInterface">
             <argument type="service" 
                       id="PluginName\Framework\Cookie\CustomCookieProvider.inner" />
         </service>
    </services>
</container>
```


**CustomCookieProvider.php**

```php
<?php declare(strict_types=1);

namespace PluginName\Framework\Cookie;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class CustomCookieProvider implements CookieProviderInterface {

    private $originalService;

    function __construct(CookieProviderInterface $service)
    {
        $this->originalService = $service;
    }
    
    private const singleCookie = [
        'snippet_name' => 'cookie.name',
        'snippet_description' => 'cookie.description ',
        'cookie' => 'cookie-key',
        'value'=> 'cookie value',
        'expiration' => '30'
    ];

    // cookies can also be provided as a group
    private const cookieGroup = [
        'snippet_name' => 'cookie.group_name',
        'snippet_description' => 'cookie.group_description ',
        'entries' => [
            [
                'snippet_name' => 'cookie.first_child_name',
                'cookie' => 'cookie-key-1',
                'value'=> 'cookie value',
                'expiration' => '30'
            ],
            [
                'snippet_name' => 'cookie.second_child_name',
                'cookie' => 'cookie-key-2',
                'value'=> 'cookie value',
                'expiration' => '60'
            ]
        ],
    ];

    public function getCookieGroups(): array
    {
        return array_merge(
            $this->originalService->getCookieGroups(),
            [ 
                self::cookieGroup,
                self::singleCookie
            ]
        );
    }
}

```

### Reacting to cookie configuration changes via JavaScript
When an user saves a cookie configuration, an event is published to the documents event emitter.
The event only contains the changeset for the cookie configuration as an object.

You can listen for this event using the following lines:

```JavaScript
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';

function eventCallback(updatedCookies) {    
    if (typeof updatedCookies.myCookie !== 'undefined') {
        // The cookie with the cookie attribute "myCookie" either is set active or from active to inactive
    } else {
        // The cookie with the cookie attribute "myCookie" was not updated
    }
}

document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, eventCallback);
```

### Cookie object
The cookie objects used in [part 1](#code-examples-to-follow-along-the-service-decoration-guide) support the following attributes:

| Attribute | Data type | Required | Description |
| --------- | --------- | -------- | ----------- |
| snippet_name | String | Yes | Key of a snippet containing the display name of a cookie or cookie group. |
| snippet_description | String | No | Key of a snippet containing a short description of a cookie or cookie group. |
| cookie | String | Yes | The internal cookie name used to save the cookie. |
| value | String  | No | If unset, the cookie will not be updated (set active or inactive) by shopware, but passed to the update event only. |
| expiration | String | No | Cookie lifetime in days. **If unset, the cookie expires with the session**. | 
| entries | Array | No | An array of cookie objects. Used to create grouped cookies. Nested groups are not supported. If using this, **the group itself should not have the attributes *cookie*, *value* and *expiration*.**. |

