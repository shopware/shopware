[titleEn]: <>(Installation and requirements)
[hash]: <>(article:e2e_testing_installation)

## General requirements

To use Shopware E2E tests, at first you need to have a Shopware 6 installation running. 
Making sure, that your tests are reliable, you should have a clean installation.

The easiest way to cleanup your installation is the initialization. Using the command `./psh.phar init` Shopware 6 gets 
initialized clean and without demo data. Installation of E2E dependencies can be accomplished separately 
by running `npm install` in the E2E folder you're using, e.g. for Shopware Administration it's 
`src/Administration/Resources/app/administration/test/e2e`.

Since our tests should run on an installation that is as close as possible to a release package, we use 
production mode. If you run the tests on a development environment, the test results may vary. 

## Shopware core setup

When you use our [Development template](https://github.com/shopware/development) we provide you some tooling scripts 
located in `dev-ops/e2e/actions` to use E2E tests more comfortably. If you use those scripts for Shopware development, 
you simply need to install Cypress using `npm install -g cypress` if not done yet. There are some differences 
depending on local environment though, which we'll cover in the following paragraphs.

The`./psh.phar` commands to run our E2E tests in CLI or in Cypress' test runner are explained 
[here](#Running-End-to-End-Tests).

### Developing with docker

If you are using docker, you don't need to install a thing: We use the 
[Cypress/Included image](https://github.com/cypress-io/cypress-docker-images/tree/master/included) 
to use Cypress in Docker completely. 

However, as we're using this image for running the test runner as well, you may need to do some configuration first. 
Based on this [guide](https://www.cypress.io/blog/2019/05/02/run-cypress-with-a-single-docker-command) you need to 
forward the XVFB messages from Cypress out of the Docker container into an X11 server running on the host machine. 
The guide shows an example for Mac; other operating systems might require different commands.

### Local environment

To use E2E tests locally, you need to set the variable `CYPRESS_LOCAL` in your `psh.yaml.override` to `true`. This way,
Cypress will recognise your environment as local, without the use of docker. Afterwards, you are able to use the same 
`./psh.phar` commands as you would do using docker-based development environment.

## Plugin setup

Depending on your environment (administration or storefront) please create the following folder structure:
```
Resources
  `-- app
    `-- <environment>
      `-- test
        `-- e2e
          `-- cypress
            |-- fixtures
            |-- integration
            |-- plugins
            `-- support
```
We will cover the use of every folder in detail in our guide [Writing E2E tests](#Writing-End-to-End-Tests). 

Within the folder `Resources/app/<environment>/test/e2e`, please run `npm init -y` to generate a `package.json` file. 
It is very convenient to place a script inside the newly created `package.json` to run the tests locally. 
Please add the following section to do so: 
```javascript
"scripts": {
   "open": "node_modules/.bin/cypress open"
},
```

Now install this package with the following command:
```
npm install @shopware-ag/e2e-testsuite-platform
```

As next step, please create a new file `e2e/cypress/plugins/index.js` with the following content:
```javascript
module.exports = require('@shopware-ag/e2e-testsuite-platform/cypress/plugins');
```

Finally, create a new file e2e/cypress/support/index.js with the following line:
```javascript
// Require test suite commands
require('@shopware-ag/e2e-testsuite-platform/cypress/support');
```
