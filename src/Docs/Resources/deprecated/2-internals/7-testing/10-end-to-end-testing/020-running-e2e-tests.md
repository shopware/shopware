[titleEn]: <>(Running End-to-End Tests)
[hash]: <>(article:e2e_testing_running)

# Running End-to-End Tests

In this guide, we will show you how to run Shopware E2E tests correctly.

## Prerequisites

In E2E tests, you should not use any demo data which was not created by yourself explicitly. So please cleanup 
your Shopware installation before testing. Cleanup means no categories, no products, no settings, nothing! 
To ensure that your Shopware installation is clean, execute `./psh.phar init`. 

On top of that, please make sure that your shop has a theme assigned. 
If using `./psh.phar e2e:open` or `run`, this is done automatically. 

## Running tests using ./psh.phar

If you use docker for your development environment, you are able to start right away. 
 
Just use the following command outside of your container to run the Cypress Test Runner:
```bash
./psh.phar e2e:open
```

If you want to run the tests in CLI, please use the following command outside your container:
```bash
./psh.phar e2e:run
```

Both commands will take care of preparation and setup of your Shopware environment and Cypress. This means:
* Assigning your Shopware theme correctly
* Clearing caches
* Creating database dump for Shopware's backup

Please keep in mind that we use `Administration` as default app environment. If you want to use `Storefront` environment,
add the following parameter:
```bash
./psh.phar e2e:open --CYPRESS_ENV=Storefront
```

## Running tests in plugins

If you want to run E2E tests in your plugin, just switch to the folder `Resources/app/<enviroment>/test/e2e` and 
execute the following command:
```bash
CYPRESS_baseUrl=<your-url> npm run open
```

`<your-url>` means the Storefront-URL of your Shopware environment.

It opens up the Cypress test runner which allows you to run and debug your tests, similar to the `e2e:open` command.
However, don't forget that you might need to adjust test cleanup and other environment-related things according
to your plugin's setup. 
