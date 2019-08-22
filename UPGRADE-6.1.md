UPGRADE FROM 6.0 to 6.1
=======================

Core
----

* If you have implemented a custom FieldResolver, you need to implement the `getJoinBuilder` method.
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria` association handling

    We removed the `$criteria` parameter from the `addAssociation` function. By setting the criteria object the already added criteria was overwritten. This led to problems especially with multiple extensions by plugins. Furthermore the function `addAssociationPath` was removed from the criteria. The following functions are now available on the criteria object:

    * `addAssociation(string $path): self`
    
        This function allows you to load additional associations. The transferred path can also point to deeper levels:
    
        `$criteria->addAssociation('categories.media.thumbnails);`
    
        For each association in the provided path, a criteria object with the corresponding association is now ensured. If a criteria is already stored, it will no longer be overwritten.

    * `getAssociation(string $path): Criteria`

        This function allows access to the criteria for an association. If the association is not added to the criteria, it will be created automatically. The provided path can also point to deeper levels:
    
        ```
        $criteria = new Criteria();
        $thumbnailCriteria = $criteria->getAssociation('categories.media.thumbnail');
        $thumbnailCriteria->setLimit(5);
        ```
        

Administration
--------------

*No changes yet*

Storefront
----------

**Changes**

* A theme must now implement the `Shopware\Storefront\Framework\ThemeInterface`.
* If your javascript lives in `Resources/storefront/script` you have to explicitly define this path in the `getStorefrontScriptPath()` method of your plugin base class as we have changed the default path to `Resources/dist/storefront/js`.

Elasticsearch
-------------

*No changes yet*
