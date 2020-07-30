[titleEn]: <>(Admin api authentication)
[hash]: <>(article:admin_api_auth)

## Authentication

The Admin API requires an authentication before using it.
It uses the [oauth2](https://oauth.net/2/) standard to authenticate users. The endpoint is located at **/api/oauth/token**.
At the end of this guide you'll find a short example about the authentication.

### Request body formats
In Shopware 6, the request body has to be JSON encoded.
It's required to use typesafe values, e.g. if the API expects an integer value, you're required to provide an actual integer.
If you're using a Date field, make sure to use an ISO 8601 compatible date format.

```json
{
    "id": "01bd7e70a50443ec96a01fd34890dcc5",
    "name": "Example product",
    "taxId": "792203a53e564e28bcb7ffa1867fb485",
    "stock": 708,
    "createdAt": "2018-09-13T10:17:05+02:00"
}
```

### Response body formats
The Admin API generally supports two response body formats. The first one is a simple JSON formatted response similar to the Shopware 5 API.
The second one is the [json:api](http://jsonapi.org/) standard. By default, the response will be in json:api format.


#### json:api
The json:api format has the **Content-Type: application/vnd.api+json**. It's the default response **Content-Type**.
The format has a rich structure that eases discovering the API without using any documentation.
It provides relationships to other resources and other extended information about the resource.
For further details refer to the [json:api](http://jsonapi.org/) standard.
You can see a shortened example response below:

```json
    {
        "data": [
            {
                "id": "01bd7e70a50443ec96a01fd34890dcc5",
                "type": "product",
                "attributes": {
                    "active": true,
                    "stock": 708,
                    "createdAt": "2018-09-13T10:17:05+02:00",
                    "manufacturerId": "f85bda8491fd4d61bcd2c7982204c638",
                    "taxId": "792203a53e564e28bcb7ffa1867fb485",
                    "price": {
                        "net": 252.94117647058826,
                        "gross": 301,
                        "linked": true
                    }
                },
                "links": {
                    "self": "http://localhost:8000/api/v3/product/01bd7e70a50443ec96a01fd34890dcc5"
                },
                "relationships": {
                    "children": {
                        "data": [],
                        "links": {
                            "related": "http://localhost:8000/api/v3/product/01bd7e70a50443ec96a01fd34890dcc5/children"
                        }
                    }
                }
            }
        ],
        "included": [
            {
                "id": "792203a53e564e28bcb7ffa1867fb485",
                "type": "tax",
                "attributes": {
                    "taxRate": 20,
                    "name": "20%",
                    "createdAt": "2018-09-13T09:54:01+02:00"
                },
                "links": {
                    "self": "http://localhost:8000/api/v3/tax/792203a53e564e28bcb7ffa1867fb485"
                },
                "relationships": {
                    "products": {
                        "data": [],
                        "links": {
                            "related": "http://localhost:8000/api/v3/tax/792203a53e564e28bcb7ffa1867fb485/products"
                        }
                    }
                }
            }
        ],
        "links": {
            "first": "http://localhost:8000/api/v3/product?limit=1&page=1",
            "last": "http://localhost:8000/api/v3/product?limit=1&page=50",
            "next": "http://localhost:8000/api/v3/product?limit=1&page=2",
            "self": "http://localhost:8000/api/v3/product?limit=1"
        },
        "meta": {
            "fetchCount": 1,
            "total": 50
        },
        "aggregations": []
    }
```

#### Simple JSON
The simple JSON format only contains essential information. The format can be requested by setting the **Accept** header to **application/json**.
You can see a shortened example below:

```json
    {
        "total": 50,
        "data": [
            {
                "taxId": "792203a53e564e28bcb7ffa1867fb485",
                "manufacturerId": "f85bda8491fd4d61bcd2c7982204c638",
                "active": true,
                "price": {
                    "net": 252.94117647058826,
                    "gross": 301,
                    "linked": true,
                    "extensions": []
                },
                "stock": 708,
                "tax": {
                    "taxRate": 20,
                    "name": "20%",
                    "createdAt": "2018-09-13T09:54:01+02:00",
                    "id": "792203a53e564e28bcb7ffa1867fb485"
                },
                "manufacturer": {
                    "catalogId": "20080911ffff4fffafffffff19830531",
                    "name": "Arnold",
                    "createdAt": "2018-09-13T10:17:04+02:00",
                    "products": null,
                    "id": "f85bda8491fd4d61bcd2c7982204c638"
                },
                "parent": null,
                "children": null,
                "id": "01bd7e70a50443ec96a01fd34890dcc5"
            }
        ],
        "aggregations": []
    }
```

#### Example: Authentication using Username and Password

The following example shows how to authenticate a user by his password.

```
POST /api/oauth/token
{
    "client_id": "administration",
    "grant_type": "password",
    "scopes": "write",
    "username": "<user-username>",
    "password": "<user-password>"
}

{
  "token_type": "Bearer",
  "expires_in": 600,
  "access_token": "xxxxxxxxxxxxxx",
  "refresh_token": "token"
}
```

#### Example: Authentication using Integration

The following example shows how to authenticate with an integration. A guide how create an integration can be found [here](https://docs.shopware.com/en/shopware-6-en/settings/system/integrationen)

```
POST /api/oauth/token
{
    "grant_type": "client_credentials",
    "client_id": "<client-id>",
    "client_secret": "<client-secret>"
}

{
  "token_type": "Bearer",
  "expires_in": 600,
  "access_token": "xxxxxxxxxxxxxx",
  "refresh_token": "token"
}
```

