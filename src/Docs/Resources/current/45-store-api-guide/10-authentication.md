[titleEn]: <>(Store api authentication)
[hash]: <>(article:store_api_auth)

## Authentication

The authentication for the Store api is really simple. Each sales channel
has an `accessKey`, which can be generated in the administration or over the admin api.
This `accessKey` is used for the authentication by providing it in the `sw-access-key`

You can test the authentication by sending a request to the `store-api/v1/context` endpoint
```
GET http://shopware.development/store-api/v3/context
--header sw-access-key SWSCYKQZODFVTVHJEHI0RFN0RG

{
	"includes": {
		"sales_channel_context": ["token"]
	}
}

{
    "token": "OCqj5ZEnpS0W9KgseOofPLqGArVjd5CE",
    "apiAlias": "sales_channel_context"
}
```

The response contains a `token`. This token is the context token which can be used to switch the different values
of your current "session" like active language, currency or shipping location.

The token can be provided in each request in the `sw-context-token` header:

```
GET http://shopware.development/store-api/v3/context
--header sw-access-key SWSCYKQZODFVTVHJEHI0RFN0RG
--header sw-context-token OCqj5ZEnpS0W9KgseOofPLqGArVjd5CE

{
	"includes": {
		"sales_channel_context": ["token"]
	}
}

{
    "token": "OCqj5ZEnpS0W9KgseOofPLqGArVjd5CE",
    "apiAlias": "sales_channel_context"
}
``` 

When you not providing the token, you will get a new session and persisted data, like the cart, will be lost.
Some Store API routes generate, for security reasons, a new token - such as the customer login route.

```
POST /store-api/v3/account/login
--header sw-access-key SWSCYKQZODFVTVHJEHI0RFN0RG
--header sw-context-token OCqj5ZEnpS0W9KgseOofPLqGArVjd5CE

{
    "username": "test@example.com",
    "password": "shopware"
}


{
    "contextToken": "RA5nC667s2QQkQ1D54UnT0fasxJ14kUj",
    "apiAlias": "array_struct"
}
```

After receiving the new token, you should provide the new token, otherwise your session data will be lost.
