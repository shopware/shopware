[titleEn]: <>(Disabled auto load of many to one associations)

We changed the default value of `$autoload` to `false` in the `\Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField`. 
Additionally we disable this flag for the most core associations to prevent unnecessary data loading. It is now required to specify which data has be loaded
on php or on javascript side. 

For the sake of all developers we added the `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociationPath` function which allows to added nested associations to the criteria:
```
$criteria = new Criteria();

// adds an empty criteria for the following associations
    // category.products
    // product.prices
    // price.rule

$criteria->addAssociationPath('products.prices.rule');

$categoryRepository->search($criteria, $context);

```

The same function exists for the admin Vue part:

```
criteria = new Criteria();

// adds an empty criteria for the following associations
    // category.products
    // product.prices
    // price.rule

criteria.addAssociationPath('products.prices.rule');

repo = this.repositoryFactory.create('category');

repo.search(criteria, Shopware.Context.api);

```
