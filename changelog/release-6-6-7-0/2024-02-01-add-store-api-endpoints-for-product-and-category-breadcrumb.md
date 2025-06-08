---
title: Add store API endpoints for product and category breadcrumbs
issue: NEXT-24954
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# API
* Added store API endpoints `/store-api/breadcrumb/{id}` for product and category breadcrumb

Example GET requests for **category**:  
`{{baseUrl}}/breadcrumb/{id}?type=category` // one DB call less, if you know the type provide it for category  
`{{baseUrl}}/breadcrumb/{id}` // but in general type is optional

Example GET requests for **product**:  
`{{baseUrl}}/breadcrumb/{id}?referrerCategoryId={categoryId}`  
`{{baseUrl}}/breadcrumb/{id}` // default type is product

If you do not provide a `referrerCategoryId` for the product, the main seo category of the product will be used.  

Response structure looks like this _(name and path are always present, seoUrls are optional)_:
```json
{
    "breadcrumb": [
        {
            "name": "Home, Jewelry & Games",
            "categoryId": "019192b9b59b713c96b80559e8838d5c",
            "type": "page",
            "translated": {
                "customFields": {},
                "slotConfig": null,
                "linkType": null,
                "internalLink": null,
                "externalLink": null,
                "linkNewTab": null,
                "description": "Deserunt similique necessitatibus illum voluptatibus fugiat voluptatem ullam. Quia iste cum sequi qui.",
                "metaTitle": null,
                "metaDescription": null,
                "keywords": null
            },
            "path": "Home-Jewelry-Games/",
            "seoUrls": [
                {
                    "id": "019192bab2247371acf6efb3af10bea8",
                    "pathInfo": "/navigation/019192b9b59b713c96b80559e8838d5c",
                    "seoPathInfo": "Home-Jewelry-Games/"
                }
            ],
            "apiAlias": "breadcrumb"
        }
    ],
    "apiAlias": "breadcrumb_collection"
}
```
