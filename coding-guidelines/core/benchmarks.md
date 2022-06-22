# Benchmarks

Since the 6.4.11 version, we have run continuous performance benchmarks via [locust.io](https://locust.io/). We want to extend these benchmarks continuously. You can find the sources in the [src/Core/Devops/Locust](https://github.com/shopware/platform/tree/trunk/src/Core/DevOps/Locust) folder. You can find all the information on setting up and running these tests in the [README.md](https://github.com/shopware/platform/blob/trunk/src/Core/DevOps/Locust/README.md).

If you develop a new feature that impacts the store (storefront and/or store API), this functionality must be performance tested. It is not enough to rest on the fact that performance tests already exist and hope they will test it somehow. New functionality usually also needs a configuration which is not inside the existing mysqldump.

## Extend `setup.php`
The [`setup.php`](https://github.com/shopware/platform/blob/trunk/src/Core/DevOps/Locust/setup.php) exports a fixture set which you can use inside the locust python files. Getting the data on demand via API would also be possible, but this would put additional load on the server and possibly falsify the results.

So if you need additional data to test your feature, you can write other .json files here. These will then be parsed in the [Context](https://github.com/shopware/platform/blob/trunk/src/Core/DevOps/Locust/common/context.py) class and made available globally.

## Extend the scenarios

We have defined several [scenarios](https://github.com/shopware/platform/tree/trunk/src/Core/DevOps/Locust/scenarios) in locust.

You have to customize the following scenarios under the following conditions:

- API-benchmark.py > Here, we test the performance of our API for ERP imports.
  - API Write / Sync processes extended
  - Indexing process of products or categories
- integration-benchmark.py > In this scenario, we test everything
  - Always
- Nvidia-benchmark.py > In this scenario, we test many orders per second
  - Changes to the order process
  - Changes to `available_stock`
  - Changes to the product detail page
- store-API-benchmark.py > In this scenario, we test our store API routes
  - If you have added a new store API route
  - If you have added a function to an existing store API
- storefront-benchmark.py > Here, we test only storefront routes without a shopping cart
  - If you have added new functions to the storefront

## Extend mysqldump

If the new feature needs new data, the mysqldump of the demo-data environment must be adjusted. For example, if you implement a new CMS element, it is essential, that this element is used somewhere. Otherwise, this element will never be considered when running the performance tests.

Since the mysqldump is static and defined as a snapshot on our servers, it is not easy to manipulate it.

Therefore, you should build a script that can manipulate an existing system to add the necessary data for you.
Let's take product ratings as an example. In the script, you would simply add ten ratings to each product, which are randomly generated.

Test the script locally and build your locust scenarios based on the results. Once you have met all criteria for a merge, create a merge request and add the @ct-core team as reviewers. You should put the script to adjust the dump in the merge request description.

@ct-core will do a review of your script and your performance tests. Once the review is successful, please contact @ct-core team to spawn the mysql server. Afterwards you can manipulate the dump and @ct-core create a new snapshot.

Since we also test other dumps, you must program the locust scenarios so that the results are not affected if your data (fixtures) does not exist. 
