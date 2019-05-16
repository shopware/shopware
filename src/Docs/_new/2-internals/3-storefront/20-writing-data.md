[titleEn]: <>(Writing data)

All routes mutating data are `POST` routes. Contrary to the data loading paradigm of the storefront (a deep nested structure) and the template organization (a deep nested structure) write operations are flat and forwarded directly from the controller to a core service. The whole picture (usually) looks like this:

![write classes](./dist/write-classes.png)

Of course the core boundary is the important bit here. If modules in the core like - lets say - the [`Cart`](./../1-core/50-checkout-process/10-cart.md) provide a divergent structure internally this structure is used instead. But always a core service related to sales channel activities exists and is used.


