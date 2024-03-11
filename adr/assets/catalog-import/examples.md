### Create a product with a new media

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "products": [
        {
            "id": "018a6b222b5a734d956fb03dda765bfa",
            "name": "My product via API",
            "productNumber": "PRODNUMAPI1",
            "tax": {
                "name": "Reduced rate 2"
            },
            "prices": [
                {
                    "currency": "EUR",
                    "gross": 10,
                    "net": 20,
                    "linked": false
                }
            ],
            "media": [
                {
                    "url": "https://images.unsplash.com/photo-1660236822651-4263beb35fa8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80",
                    "title": "pommes",
                    "alt": "alt",
                    "filename": "pommes.jpg"
                }
            ]
        }
    ]
}
```

### Update a product and update its media

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "media": [
        {
            "id": "018a6b222b5a734d956fb03dda765bfb",
            "title": "New title"
        }
    ],
    "products": [
        {
            "id": "018a6b222b5a734d956fb03dda765bfa",
            "name": "My update product"
        }
    ]
}
```

### Delete a product and media

```http
POST http://localhost:8000/api/import/{{import_id}}/record/delete
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}
{
    "products": [
        "018a6b222b5a734d956fb03dda765bfa"
    ],
    "media": [
        "018a6b222b5a734d956fb03dda765bfb"
    ]
}
```

### Create a product with a custom entity

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
  "products": [
    {
      "id": "018a6b222b5a734d956fb03dda765bfa",
      "name": "My product via API",
      "productNumber": "PRODNUMAPI1",
      "tax": {
        "name": "Reduced rate 2"
      },
      "prices": [
        {
          "currency": "EUR",
          "gross": 10,
          "net": 20,
          "linked": false
        }
      ],
      "extensions": {
        "myCustomEntity": {
          "id": "018a6b222b5a734d956fb03dda765bf8",
          "name": "foo"
        }
      }
    }
  ]
}
```

### Update a custom entity

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "extensions": {
        "myCustomEntity": [
          {
            "id": "018a6b222b5a734d956fb03dda765bf8",
            "name": "bar"
          }
        ]
    },
    "products": [
        {
            "id": "018a6b222b5a734d956fb03dda765bfa",
            "name": "My update product"
        }
    ]
}
```

### Create a category and assign products to it

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "categories": [
        {
            "name": "Category 1",
            "parent": [
                "Home",
                "Category 2",
                "Category 3"
            ],
            "products": [
                {
                    "id": "prod1d1"
                },
                {
                    "id": "prod1d2"
                }
            ]
        }
    ]
}
```

### Un-assign a media from a product

```http
POST http://localhost:8000/api/import/{{import_id}}/record/unassign
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "products": {
        "id": "productId",
        "media": [
            {
                "id": "mediaId1"
            },
            {
                "filename": "pommes.jpg"
            }
        ]
    }
}
```

### Error Response: Resolving Root Entities

Scenario: Updating a product which does not exist

Request

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "products": [
        {
            "id": "018a6b222b5a734d956fb03dda765bfa",
            "productNumber": "PRODNUMAPI1",
        }
    ]
}
```

Response

```json
{
    "containsErrors": true,
    "records": [
        {
            "errors": [
                {
                    "message": "ID 018a6b222b5a734d956fb03dda765bfa not found",
                    "path": "products.0"
                }
            ]
        }
    ]
}
```

### Error Response: Resolving Nested Entities

Scenario: Product with ID `prod1d1` does not exist

Request

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "categories": [
        {
            "name": "Category 1",
            "parent": [
                "Home",
                "Category 2",
                "Category 3"
            ],
            "products": [
                {
                    "id": "prod1d1"
                },
                {
                    "id": "prod1d2"
                }
            ]
        }
    ]
}
```

Response

```json
{
    "containsErrors": true,
    "records": [
        {
            "id" : "c642f3515aff4783991e361f381e77ca",
            "errors": [
                {
                    "message": "ID prod1d1 not found",
                    "path": "categories.0.products.1"
                },
                {
                    "message": "ID prod1d2 not found",
                    "path": "categories.0.products.2"
                }
            ]
        }
    ]
}
```


### Error Response Status Root

Scenario: Create a product, productNumber is not unique

Request

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "products": [
        {
            "id": "018a6b222b5a734d956fb03dda765bfa",
            "name": "My product via API",
            "productNumber": "PRODNUMAPI1",
            "tax": {
                "name": "Reduced rate 2"
            },
            "prices": [
                {
                    "currency": "EUR",
                    "gross": 10,
                    "net": 20,
                    "linked": false
                }
            ],
        }
    ]
}
```

Response

```json
{
    "status": "done",
    "startTime": "25/12/2024",
    "duration": "10",
    "totals": {
        "product": 2,
        "media": 1,
        "total": 3,
        "failures": 1
    },
    "failures": [
        {
            "entity": "product",
            "path": "products.0",
            "details": [
                {
                    "severity": "error",
                    "entity": "product",
                    "path": "products.0",
                    "message": "Product number is not unique"
                }
            ]
        }
    ]
}
```

### Error Response Status Nested

Scenario: Create a product with a media, media fails to download

Request

```http
POST http://localhost:8000/api/import/{{import_id}}/record
Content-Type: application/json
Accept: application/json
Authorization: Bearer {{auth_token}}

{
    "products": [
        {
            "id": "018a6b222b5a734d956fb03dda765bfa",
            "name": "My product via API",
            "productNumber": "PRODNUMAPI1",
            "tax": {
                "name": "Reduced rate 2"
            },
            "prices": [
                {
                    "currency": "EUR",
                    "gross": 10,
                    "net": 20,
                    "linked": false
                }
            ],
            "media": [
                {
                    "url": "https://images.unsplash.com/photo-1660236822651-4263beb35fa8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80",
                    "title": "pommes",
                    "alt": "alt",
                    "filename": "pommes.jpg"
                }
            ]
        }
    ]
}
```

Response

```json
{
    "status": "done",
    "startTime": "25/12/2024",
    "duration": "10",
    "totals": {
        "product": 2,
        "media": 1,
        "total": 3,
        "failures": 1
    },
    "failures": [
        {
            "entity": "product",
            "path": "products.0",
            "details": [
                {
                    "severity": "error",
                    "entity": "product",
                    "path": "products.0",
                    "message": "Images could not be downloaded"
                },
                {
                    "severity": "error",
                    "entity": "media",
                    "path": "products.0.media.0",
                    "message": "Image %s could not be downloaded"
                },
                {
                    "severity": "error",
                    "entity": "media",
                    "path": "products.0.media.1",
                    "message": "Image %s could not be downloaded"
                }
            ]
        }
    ]
}
```
