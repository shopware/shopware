[wikiUrl]: <>(../shopware-platform-en/using-the-api/introduction?category=shopware-platform-en/using-the-api)
[titleEn]: <>(['Introduction'])
The API can be used to complete all administrative tasks, like creating
products, updating prices and much more. For building a storefront or
extending it, you can use the [Storefront
API](./../40-storefront-api/10-introduction.md)

The API makes it really easy to integrate *Shopware* into your
environment.

## Getting started

The API requires you to authenticate before using it. The API uses the
[oauth2](https://oauth.net/2/) standard to authenticate users. The
endpoint is at **/api/oauth/token**

### Authentication example

The following example shows how to authenticate a user by password.
Detailed information about the authentication can be found
[here](./20-authentication.md).

```php
<?php
    const baseUrl = '{insert your url}';
    const data = {
        client_id: "administration",
        grant_type: "password",
        scopes: "write",
        username: "admin",
        password: "shopware"
    };
    const init = {
        method: 'POST',
        body: JSON.stringify(data),
        headers: { "Content-Type": "application/json" }
    };
    fetch(`${baseUrl}/api/oauth/token`, init)
        .then((response) => response.json())
        .then(({ access_token }) => {
            console.log('access_token', access_token);
        });
```
### Fetching Products

After fetching an access token you can access all other resources by
sending the access token in the **Authorization** header. The following
example shows how to get a list of products. For detailed information
take a look at [Standard
Resources](./30-standard-resources.md)
and [Special
Resources](./40-special-resources.md).

```php
<?php
    const baseUrl = '{insert your url}';
    const data = {
        client_id: "administration",
        grant_type: "password",
        scopes: "write",
        username: "admin",
        password: "shopware"
    };
    const init = {
        method: 'POST',
        body: JSON.stringify(data),
        headers: { "Content-Type": "application/json" }
    };
    fetch(`${baseUrl}/api/oauth/token`, init)
        .then((response) => response.json())
        .then(({ access_token }) => {
            const headers = { Authorization: "Bearer " + access_token };
            fetch(`${baseUrl}/api/v1/product`, { headers })
                .then((response) => response.json())
                .then((products) => console.log('Products', products))
        });
```

## Response body formats

The API generally supports two response body formats. The first one is a
simple JSON formatted response similar to the Shopware 5 API. The second
one is the [json:api](http://jsonapi.org/) standard. By default, the
response will be in json:api format.

### json:api

The json:api format has the **Content-Type: application/vnd.api+json**.
Its the default response **Content-Type**. The format has a rich
structure that eases discovering the API without using any
documentation. It provides relationships to other resources and other
extended information about the resource. For further details refer to
the [json:api](http://jsonapi.org/) standard. You can see a shortened
example response below:
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
                    "self": "http://localhost:8000/api/v1/product/01bd7e70a50443ec96a01fd34890dcc5"
                },
                "relationships": {
                    "children": {
                        "data": [],
                        "links": {
                            "related": "http://localhost:8000/api/v1/product/01bd7e70a50443ec96a01fd34890dcc5/children"
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
                    "self": "http://localhost:8000/api/v1/tax/792203a53e564e28bcb7ffa1867fb485"
                },
                "relationships": {
                    "products": {
                        "data": [],
                        "links": {
                            "related": "http://localhost:8000/api/v1/tax/792203a53e564e28bcb7ffa1867fb485/products"
                        }
                    }
                }
            }
        ],
        "links": {
            "first": "http://localhost:8000/api/v1/product?limit=1&page=1",
            "last": "http://localhost:8000/api/v1/product?limit=1&page=50",
            "next": "http://localhost:8000/api/v1/product?limit=1&page=2",
            "self": "http://localhost:8000/api/v1/product?limit=1"
        },
        "meta": {
            "fetchCount": 1,
            "total": 50
        },
        "aggregations": []
    }
```

### Simple JSON

The simple JSON format only contains essential information. The format
can be requested by setting the **Accept** header to
**application/json**. You can see a shortened example below:
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
                    "id": "792203a53e564e28bcb7ffa1867fb485",
                },
                "manufacturer": {
                    "catalogId": "20080911ffff4fffafffffff19830531",
                    "name": "Arnold",
                    "createdAt": "2018-09-13T10:17:04+02:00",
                    "products": null,
                    "id": "f85bda8491fd4d61bcd2c7982204c638",
                },
                "parent": null,
                "children": null,
                "id": "01bd7e70a50443ec96a01fd34890dcc5",
            }
        ],
        "aggregations": []
    }
```