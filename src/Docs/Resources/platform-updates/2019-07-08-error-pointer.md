[titleEn]: <>(Error pointer returns write index)

Shopware 6 uses batch operations for every action the DAL. Therefore, the paths in the exceptions are now prefixed with their write index of the batch operation. This also applies to the response in the API which introduces a major break for existing error parser.

**Before**

`/name`

**After**

`/0/name`
