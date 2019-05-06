[titleEn]: <>(New E2E commands for select components)

There are two new custom E2E commands for the new `sw-single-select` and `sw-multi-select` components:

```
.fillMultiSelect(
    '.selector', 
    'Search term', 
    'Value'
);

.fillSingleSelect(
    '.selector', 
    'Value', 
    1 /* Desired result position */
);
```

Those are also valid when using the **entity** select components.

