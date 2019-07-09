[titleEn]: <>(Updated Entity Extension Handling)

We have updated the handling of Entity Extensions.

## Breaking Changes

1. It is no longer possible to add Fields, that need an DB column on the extended Entity, as Extension.
    
    This means that you are not allowed to add any scalar value fields, like `StringField` or `FkField`, as an Extension anymore.
    The only exception are fields flagged as `Runtime()` as these fields are not stored in the Database, but evaluated and set at runtime.
    
    The reason behind this decision is that you were forced to edit the schema of the tables you wanted to extend, which is very fragile and can lead to a number of problems.
    
2. Extensions are an own Relationship in JSON:API responses.
    
    Previously the extensions were json serialized and put under the `extension` key in JSON:API responses.
    This wasn't quite compatible with the JSON:API spec as all relationships added as extension were also json serialized and added to every entity.
    
    To use the auto-discovery and deduplication mechanisms of the JSON:API spec we decided to add the extensions as an own Relationship, that can have references to other entities.
    
    Before:
    ```json
    {
       "data": {
         "id": "1d23c1b015bf43fb97e89008cf42d6fe",
         "type": "product",
         "attributes": {
           "name": "My awesome Product",
           ...
           "extensions": {
             "seoUrls": [
               {
                 "id": "ffffffffffffffffff",
                 ...
               },
               {
                 "id": "1111111111111111",
                 ...
               }
             ]
           }
         },
         "relationships": {...}
       }
    }
    ```
    
    Now:
    ```json
    {
       "data": {
         "id": "1d23c1b015bf43fb97e89008cf42d6fe",
         "type": "product",
         "attributes": {
           "name": "My awesome Product",
           ...
         },
         "relationships": {
           ...
           "extensions": {
             "data": {
               "type": "extension",
               "id": "1d23c1b015bf43fb97e89008cf42d6fe"
             }
           }
         }
       },
       "included": [
         {
           "id": "1d23c1b015bf43fb97e89008cf42d6fe",
           "type": "extension",
           "attributes": {},
           "relationships": {
             "seoUrls": {
               "data": [
                   {
                     "type": "seo_url",
                     "id": "ffffffffffffffffff"
                   },
                   {
                     "type": "seo_url",
                     "id": "1111111111111111"
                   }
               ]
             }
           }
         },
         {
           "id": "ffffffffffffffffff",
           "type": "seo_url",
           "attributes": {...},
           "relationships": {...}
         },
         {
           "id": "1111111111111111",
           "type": "seo_url",
           "attributes": {...},
           "relationships": {...}
         }
       ]
    }
    ```
    
## Fixes

1. You can now add nested Associations from your ToOne-Extensions to your criteria object.

    This previously lead to an error:
    ```php
       $criteria->addAssociationPath('myToOneExtension.myNestedAssociation');
    ```
    
2. The new data handling works out of the box with `EntityExtensions`
    
    Previously you got plain arrays or JS-Objects inside the administration if you accessed your extensions with e.g. `this.product.extensions.seoUrls`. 
    This lead to problems with the data handling as the data handling expects either `Entity`-Objects or `EntityCollection`.
    
    With the previously described changes in the JSON:API responses the new data handling now automatically hydrates you extensions into `Entity`-Objects or `EntityCollection`.
    So now you would get an `EntityCollection` back when you access you extension with `this.product.extensions.seoUrls` and the data handling just works with your extensions.