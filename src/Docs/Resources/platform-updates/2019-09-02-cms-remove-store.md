[titleEn]: <>(Shopping Experiences - New data handling)

The `sw-cms` module has been moved to the new data handling. To get an entity resolved in an element you now need to configure a configfield like this: 
```
product: {
  source: 'static',
  value: null,
  required: true,
  entity: 
    { 
      name: 'product',
      criteria: criteria
    }
  }
```

in the `cmsService.registerCmsElement` 's defaultConfig. Where the criteria is a criteria instance with the required criterias for this entity (in this case `const criteria = new Criteria(); criteria.addAssociation('cover');`).

For each element you can define your custom `collect` and `enrich` method in the `cmsService.registerCmsElement` method to add custom logic if required. If none is defined the `cmsService` will add the default methods. These methods are required to resolve the entitydata in the new `cmsDataResolverService`.

The `cmsDataResolverService` fetches the data for each element with the new data handling. `resolve` is called from the `sw-cms-detail` page (in the loadData method). Here all `collect` methods from the elements are executed.  After this, the `optimizeCriteriaObjects` seperates the required entites by criteria and checks if the can be merged. After this, alle entites are fetched, either by id (if no criteria is given) or by criteria. At last the resolved entities are distributed back to the elements and the `enrich` method of each element is called.


To get an example of a custom enrich method you can look up the `module/sw-cms/elements/image-slider/index.js` 

**Sorry for the inconvenience!**
