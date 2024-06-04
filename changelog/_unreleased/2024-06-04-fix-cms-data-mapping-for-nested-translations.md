### 1. Why is this change necessary?
In the shopping experience I set a data mapping for an mapped entity value (e.g. product.manufacturer.name).

In storefront for the default language works everything fine (because of product.manufacturer.name is filled). But in any other language it don't works (because of product.manufacturer.name is not filled and it don't reads translated value).

### 2. What does this change do, exactly?
It reads the correct translated value of nested entties inside the cms data mapping.

### 3. Describe each step to reproduce the issue or behaviour.
1. Create a text shopping experience element inside a cms product detail page.
2. Inside the shopping experience add a new default text element. Select the data mapping and map product.manufacturer.name there.
3. Assign this shopping experience to a product, which has a manufacturer assigned
4. Open the product in the storefront in default language -> there everything works fine and you see the manufacturer name like it should be.
5. Now change the language in the storefront and check againg -> now you see the product name and not the manufacturer name.

### 4. Please link to the relevant issues (if any).
https://issues.shopware.com/issues/NEXT-36499

### 5. Checklist

- [x] I have rebased my changes to remove merge conflicts
- [ ] I have written tests and verified that they fail without my change
- [x] I have created a [changelog file](https://github.com/shopware/platform/blob/trunk/adr/2020-08-03-implement-new-changelog.md) with all necessary information about my changes
- [ ] I have written or adjusted the documentation according to my changes
- [ ] This change has comments for package types, values, functions, and non-obvious lines of code
- [x] I have read the contribution requirements and fulfil them.
