# Benchmarks

Since the 6.4.11 version, we run continuous performance benchmarks via [locust.io](https://locust.io/). We want to extend these benchmarks continuously. The corresponding sources are located in the [Devops/Locust](https://github.com/shopware/platform/tree/trunk/src/Core/DevOps/Locust) folder. All information on how to set up and run these tests can be found in the [README.md](https://github.com/shopware/platform/blob/trunk/src/Core/DevOps/Locust/README.md).

If a new feature is developed that impacts the store (storefront and/or store API), then this functionality must be performance tested. It is not enough to rest on the fact that performance tests already exist and hope that they will test it somehow. New functionality usually also needs a configuration which is not in the mysqldump used for the continuous tests.

## Extend `setup.php`
The [`setup.php`](https://github.com/shopware/platform/blob/trunk/src/Core/DevOps/Locust/setup.php) file exports a fixture set which can be used inside the locust python files. It would also be possible to get the data on demand via API, but this would put additional load on the server and possibly falsify the results.

So if you need additional data to test your feature, you can write additional .json files here. These will then be parsed in the [Context](https://github.com/shopware/platform/blob/trunk/src/Core/DevOps/Locust/common/context.py) class and made available globally.

## Extend the scenarios

We have defined several [scenarios](https://github.com/shopware/platform/tree/trunk/src/Core/DevOps/Locust/scenarios) in locust.

The following scenarios need to be customized under the following conditions:

- api-benchmark.py > Here we test the performance of our API for erp imports.
  - API Write / Sync processes extended
  - Indexing process of products or categories
- integration-benchmark.py > In this scenario we test everything
  - Always
- nvidia-benchmark.py > In this scenario we test many orders per second
  - Changes to the order process
  - changes to `available_stock`
  - changes to product detail page
- store-api-benchmark.py > In this scenario we test our store API routes
  - If you have added a new store api route
  - If you have added a function to an existing store API
- storefront-benchmark.py > Here we test only storefront routes without a shopping cart
  - If you have added new functions to the storefront

## Extend mysqldump

If the new feature needs new data, the mysqldump of the demo-data environment must be adjusted for this. For example, if a new CMS element is implemented, it is important that this element is used somewhere. Otherwise, this element will never be taken into account when running the performance tests.

Since the mysqldump is static and defined as a snapshot on our servers, it is not easy to manipulate it.

Therefore, you should build a script that can manipulate an existing system to add the necessary data for you.
Let's take product ratings as an example. In the script you would simply add 10 ratings to each product, which are randomly generated.

Test the script locally and build your locust scenarios based on the results. Once you feel you have met all the criteria for a merge, please create a merge request and add the @ct-core team as reviewers. You should put the script to adjust the dump in the merge request description.

A review of your script and your performance tests will be done. Once the review is successful, the @ct-core team will contact you to customize the demo dump with your script and create a new snapshot.

Since we also test other dumps, you must program the locust scenarios so that if your data (fixtures) does not exist, the results are not affected. 