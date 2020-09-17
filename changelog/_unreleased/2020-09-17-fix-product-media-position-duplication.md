---
title:         Fix bug when images are add to a product the position 1 can be assigned multiple times
issue:         NEXT-9292
author:        Joshua Behrens
author_email:  code@joshua-behrens.de
author_github: JoshuaBehrens
___
# Administration
* Match procedure to assign `product_media.position` in `sw-product-media-form` and `sw-product-detail` so it is always set to prevent a bug where the position 1 is automatically assigned which results in unexpected sorting and selection behaviours
___
# Upgrade Information

## Am I affected of this bug?

To check whether you are affected of this position bug you can use the following SQL to check your product images for this bug.
With this information you can fix the order accordingly using the drag and drop functionality in the administration:
```sql
SELECT
    LOWER(HEX(product_id)) product_id,
    GROUP_CONCAT(DISTINCT product_number, ';') product_number,
    position,
    COUNT(1) ducplicates
FROM
    product_media
LEFT JOIN
    product
ON
    product_media.product_id = product.id AND product_media.product_version_id = product.version_id
GROUP BY
    product_id,
    position
HAVING
    ducplicates > 1
```
