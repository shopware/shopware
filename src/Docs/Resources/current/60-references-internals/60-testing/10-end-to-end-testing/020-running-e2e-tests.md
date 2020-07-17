[titleEn]: <>(Running End-to-End Tests)
[hash]: <>(article:e2e_testing_running)

In this guide, we will show you how to run Shopware E2E tests correctly.

## Prerequisites

In E2E tests, you should not use any demo data which was not created by yourself explicitly. So please cleanup 
your Shopware installation before testing. Cleanup means no categories, no products, no settings, nothing! 
To ensure that your Shopware installation is clean, execute `./psh.phar init`. 

On top of that, please make sure that your shop has a theme assigned. 
If using `./psh.phar e2e:open` or `run`, this is done automatically. 

## Running tests using ./psh.phar

If you use docker for your development environment, you are able to start right away. 

To prepare your shopware installation, your environment and install dependencies, please run the following command as
first step, **outside** of your docker container:
 ```bash
 ./psh.phar e2e:init
 ```

In our tests, we assume a clean shopware installation, so we strongly recomment to use `e2e:init`. However, if your 
shopware installation is already clean and prepared, you can skip the preparation of your shopware installation 
by using the following command **inside** your docker container:
 ```bash
 ./psh.phar e2e:prepare-environment
 ```
 
Afterwards, just use the following command outside of your container to run the Cypress Test Runner:
```bash
./psh.phar e2e:open
```

If you want to run the tests in CLI, please use the following command outside your container:
```bash
./psh.phar e2e:run
```

Please keep in mind that we use `Administration` as default app environment. If you want to use `Storefront` environment,
add the following parameter:
```bash
./psh.phar e2e:open --CYPRESS_ENV=Storefront
```

### Overview of ./phar.phar E2E commands

| Command        | Description           | 
| -------------- |-------------------- | 
| ./psh.phar e2e:cleanup | Sets Shopware back to state of the backup |
| ./psh.phar e2e:dump-db | Creates a backup of Shopware's database |
| ./psh.phar e2e:init | Prepares Shopware installation and environment for Cypress usage |
| ./psh.phar e2e:open | Opens Cypress' e2e tests runner |
| ./psh.phar e2e:prepare-environment | Install dependencies and prepare database for Cypress usage |
| ./psh.phar e2e:prepare-shopware | Prepare shopware installation for Cypress usage |
| ./psh.phar e2e:restore-db | Restores shopware backup |
| ./psh.phar e2e:run | Runs Cypress' e2e tests in CLI |

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
