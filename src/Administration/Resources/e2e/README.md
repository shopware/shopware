# sw-cypress

This is a project dealing with the implementation of E2E tests for the [Shopware platform project](https://github.com/shopware/platform) using Cypress framework.

## Setup
Shopware platform itself is not shipped with this project. This way, you need a running environment with a shopware platform repository. For more details about the setup steps, please refer to Shopware platform's [getting started guide](https://docs.shopware.com/en/shopware-platform-dev-en/getting-started).

At first, clone this project in a folder you like. After that, you can just run the tests in a Docker container (see below).

## One thing to keep in mind

Please notice that these Cypress tests in `cypress/integration/administration` rely on a clean installation without any custom or demo data. 

When it comes to storefront tests, they don't rely on a specific dataset, but do need at least one entity of a kind to be available! E.g. one product, one customer, etc.

One possibility to fulfill these requirements is the following

| shell helper | test folder    |
| ------------ |:--------------:|
| psh install  | storefront     |
| psh init     | administration |

## Install Cypress

The easiest way to install Cypress and all npm dependencies into your project folder is this docker helper:

```
docker run -ti --rm -v "$(pwd)":/cypress -v npm-root-cache:/root/.cache --workdir /cypress cypress/browsers:node11.13.0-chrome73 npm ci
```

You can also follow the [Cypress installation guide](https://docs.cypress.io/guides/getting-started/installing-cypress.html) for a manual installation. 

## Configure Cypress

### Environment 
Some environment variable are needed to run Cypress with Shopware platform properly. Those environment variables have
to be available thorough a `cypress.env.json` file. 

We provide an example you can use out of the box. 
Therefore, just copy `cypress.env.json.example` in the same folder and remove the `.example` of the file name in the 
process. Afterwards, feel free to configure the environment variables according to your needs. 

### Appearance
By default, we use the shopware theme for Cypress test runner. IF you don't want to use it, please set the following to `false`:
```
"useShopwareTheme": true
```

In this test suite, we integrated [cypress-dark plugin](https://github.com/bahmutov/cypress-dark) 
to provide a dark mode for the test runner as well. You can activate it through setting the following variable in `cypress.json`:
```
"useDarkTheme": false
```

## Run Cypress

### Run tests in Docker

Now you can run your tests in a Docker container:

```
docker run -ti --rm -v "$(pwd)":/cypress -v npm-root-cache:/root/.cache --workdir /cypress cypress/browsers:node11.13.0-chrome73 ./node_modules/cypress/bin/cypress run
```

### Run tests on your machine (and watch them)

You can also run the tests on your machine and watch them running with `cypress run`.


### Run only Storefront-Tests

If you only want to run the Storefront-Tests simply add `--spec "cypress/integration/storefront/**/*"` to the `cypress open` command.

### Run tests against a remote URL

You can override the baseUrl to test a remote Shopware Installation by passing the `--config baseUrl=https://<remote-hostname>/` param.

## Further information

* [Cypress documentation](https://docs.cypress.io/guides/overview/why-cypress.html)
* [Shopware platform documentation](https://docs.shopware.com/en/shopware-platform-dev-en)
* [Shopware development template](https://github.com/shopware/development)
* [Shopware platform project](https://github.com/shopware/platform) 
