---
title: Introduce oauth api client confidential flag
issue: NEXT-40677
---
# Core
* Added nullable boolean parameter `confidential` to `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient` so the client can be configured based on different grant types.
* Deprecated passing null as `confidential` in `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient`. Only boolean values will be allowed. The parameter will also move to position three.
* Deprecated passing `name` as the third parameter to `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient`. It will be moved to position four.
___
# Upgrade Information
## ApiClient confidential flag

The `confidential` flag of `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient` will be required and will be moved to position three. You can update your implementations to pass the correct value and use named parameters to avoid having to update the implementation again when the deprecations are fixed:

```php
$client = new \Shopware\Core\Framework\Api\OAuth\Client\ApiClient(
    'my-identifier',
    true,
    name: 'My Client',
    confidential: true
);     
```
___
# Next Major Version Changes
## ApiClient confidential flag

* You must explicitly pass a boolean value to the `confidential` parameter  of `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient`.
* You must pass the `confidential` parameter as the third parameter of the constructor.
* You must pass the `name` parameter as the fourth parameter of the constructor.
```
