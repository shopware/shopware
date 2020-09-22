---
title: Storefront Store-API Proxy
issue: NEXT-10272
---
# Core
* Added `\Shopware\Storefront\Controller\StoreApiProxyController` for proxying Store-API calls in the Storefront with the correct context
___
# Upgrade Information
## HTTP Client for Store API
Use the HTTP client in your Javascript for calls to the Store API.

Example usage:
```javascript
import StoreApiClient from 'src/service/store-api-client.service';
const client = new StoreApiClient;
client.get('/store-api/v2/country', function(response) {
  console.log(response)
});
```
