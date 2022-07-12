<!--
Thank you for contributing to Shopware! Please fill out this description template to help us to process your pull request.

Please make sure to fulfil our contribution guideline (https://developer.shopware.com/docs/resources/guidelines/code/contribution?category=shopware-platform-dev-en/contribution).

Do your changes need to be mentioned in the documentation?
Please create a second pull request at https://github.com/shopware/docs
-->

### 1. Why is this change necessary?
In the shopping world experiences you have the possibility to change elements within blocks. However the outer block wrapper is not being changed if you do so. 
If you drag & drop a Cross-Selling CMS-Block into the shopping world and then change the element within the block, nothing is being rednered in the storefront due to the if-statement.

In the Cross-Selling CMS-Block there also the check see [Cross Selling CMS-Element](https://github.com/shopware/platform/blob/trunk/src/Storefront/Resources/views/storefront/element/cms-element-cross-selling.html.twig)
```
{% if element.data.crossSellings.elements is defined %}
    {% for item in element.data.crossSellings.elements|filter(item => item.total > 0 and item.crossSelling.active == true) %}
```

### 2. What does this change do, exactly?
Removing the if-statement from the cross-selling block frontend-view to also get other elements rendered in the storefront if you change the element within that block

### 3. Describe each step to reproduce the issue or behaviour.
Go to the shopping experience drop a cross-selling block and then change the element within the block. Now visit the storefront and check if you see the changed element in the storefront


### 4. Please link to the relevant issues (if any).
[Cross Selling CMS-Element](https://github.com/shopware/platform/issues/2597)

### 5. Checklist

- [ ] I have written tests and verified that they fail without my change
- [x] I have created a [changelog file](https://github.com/shopware/platform/tree/trunk/changelog/_unreleased/2022-07-12-cross-selling-cms-block-frontend-rendering.md) with all necessary information about my changes
- [ ] I have written or adjusted the documentation according to my changes
- [ ] This change has comments for package types, values, functions, and non-obvious lines of code
- [x] I have read the contribution requirements and fulfil them.