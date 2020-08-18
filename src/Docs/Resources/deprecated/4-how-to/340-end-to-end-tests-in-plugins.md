[titleEn]: <>(End-to-end testing in plugins)
[metaDescriptionEn]: <>(To ensure the main functionality on workflow level, it's useful to write some end-to-end tests for your plugin. Here you can find a starting point for such an end-to-end testing environment for plugins.)
[hash]: <>(article:how_to_e2e_tests_plugins)

## Overview

To ensure your plugin's functionality, it's highly recommended to automatically test your source code. In the following
lines, we want to show you the way we take care of end to end testing using [Cypress](https://www.cypress.io/) 
testing framework.

This HowTo requires you to have a proper working plugin first. Furthermore, a basic knowledge of Cypress is recommended.
However, you can always look up necessary information in [Cypress documentation](https://docs.cypress.io).

## Setup

We will skip the part on how to create a plugin in this guide, so please head over to our 
[Plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md) to create your plugin first. 
In this example, we will use that quick start plugin as a basis.

## Set up Cypress

Depending on your environment (administration or storefront) please create the following folder structure:
```bash
Resources
 `-- app
   `-- <environment>
     `-- test
       `-- e2e
```

In the `e2e` directory (or any other you want to store your end-to-end tests), you can install Cypress by using 
the following command;
```bash
npm install cypress
```

There's one more thing to do in this directory: Please run `npm init -y` to generate a package.json file. It is 
very convenient to place a script inside the newly created package.json to run the tests locally. 
Please add the following script section to do so:
```javascript
"scripts": {
   "open": "node_modules/.bin/cypress open"
},
```

Now install this package with the following command:
```bash
npm install @shopware/e2e-testsuite-platform
```

As next step, please create a new file `e2e/cypress/plugins/index.js` with the following content:
```javascript
module.exports = require('@shopware/e2e-testsuite-platform/cypress/plugins');
```

Finally, create a new file `e2e/cypress/support/index.js` with the the following line:
```javascript
// Require test suite commands
require('@shopware/e2e-testsuite-platform/cypress/support');
```

### Configuration and directory structure

In the `e2e` directory (or any other you want to store your end-to-end tests), you should now see a directory structure 
similar to this one:
```
<e2e>
└── cypress
    └── fixtures
    └── integration
    └── plugins
    └── support
└── cypress.json
```
* `fixtures`: Here you can save and access your test's fixture data
* `integration`: In here, the actual test files are located.
* `plugins`: If you want to extend Cypress, this is the place to add plugins.
* `support`: Here, all custom commands, page obejct and other helpers are stored. The `cypress/support/index.js` 
will be run before every test. 
* `cypress.json`: This is the configuration file.

Important: Please make sure to let Cypress know what the `baseUrl` of your project is, where the tests will be launched
on. You can define it in `cypress.json` or set it using the parameter `--config baseUrl=http://localhost:8000`. 
More information about Cypress environment variables can be found 
 [here](https://docs.cypress.io/guides/guides/environment-variables.html).

## Writing a test

### Test structure

A test file or `spec` file consists of the following structure you might know from 
[Mocha](https://docs.cypress.io/guides/guides/environment-variables.html):

```javascript
   describe('Test: This is my test file', () => {
   
     it('test one thing', () => {
        // This is your first test
     });
     
     it('tests another thing', () => {
        // This is your second test
     });
   });
```
The test interface borrowed from Mocha allows you the usage of `describe()` (or `context()` as another way of writing),
providing a way to keep tests easier to read and organized. 

You write your test in those `it()` (or `specify()` as alias) paragraphs. This way, one Cypress test spec can include
more than one test, according to your test design.

Let's assume you want to test if you can edit your plugin's configuration and save this change successfully. So let's
start with the basic structure of your test. According to the structure above, our `spec` file will look like this:

```javascript
/// <reference types="Cypress" />

describe('PluginCypressTests: Test configuration', () => {
  it('edit plugin\'s configuration', () => {
      // Here we want to write all steps we need to test it ...
  });
});
```
Ok, looks great. Let's fill our test with life!

### Commands and assertions

In Cypress, you use commands and assertions to portrait the workflow you want to test:
* [Commands](https://docs.cypress.io/guides/core-concepts/introduction-to-cypress.html#Chains-of-Commands) are the 
actions you need to do in order to interact with the elements of your application and
reproduce the workflow to test in the end 
* [Assertions](https://docs.cypress.io/guides/core-concepts/introduction-to-cypress.html#Assertions) 
describe the desired state of your elements, your objects, and your application. 

To use both in your test, you are able to chain commands together. In addition, please use assertion to control if your 
plugin behaves according to your needs. Let us show you an example:
```javascript
cy.get('.sw-data-grid__row--0 .sw-plugin-table-entry__title') // Command to find the title element
  .contains('Label for the plugin PluginCypressTests'); // Command to check the text of the element you found before
```
The `cy.get()` will be chained onto the `.contains`, telling it to check the text of the subject yielded from the 
`cy.get()` command, which will be a DOM element. Please note you can chain assertions as well. 

Cypress bundles [Chai](https://docs.cypress.io/guides/references/bundled-tools.html#Chai), 
[Chai-jQuery](https://docs.cypress.io/guides/references/bundled-tools.html#Chai-jQuery) and 
[Sinon-Chai](https://docs.cypress.io/guides/references/bundled-tools.html#Sinon-Chai) to provide built-in 
assertions, so the syntax may sound familiar:
```javascript
 cy.get('.sw-context-menu').should('be.visible'); // Assert if the context menu element is visible
```

This way, we portrait the whole workflow of our test:
```javascript
it('edit plugin\'s configuration', () => {
    // Request we want to wait for later
    cy.server();
    cy.route({
        url: '/api/v3/_action/system-config/batch',
        method: 'post'
    }).as('saveData');

    // Open plugin configuration
    cy.get('.sw-data-grid__row--0 .sw-plugin-table-entry__title')
        .contains('Label for the plugin PluginCypressTests');

    cy.get('.sw-data-grid__row--0').should('be.visible');
    cy.get('.sw-data-grid__row--0 .sw-context-button__button').click({force: true});
    cy.get('.sw-context-menu').should('be.visible');
    cy.contains('Config').click();
    cy.get('.sw-context-menu').should('not.exist');

    // Edit configuration and save
    cy.get('input[name="PluginCypressTests.config.example"]').type('Typed using an E2E test');
    cy.get('.sw-plugin-config__save-action').click();

    cy.wait('@saveData').then(() => {
        cy.get('.sw-notifications__notification--0 .sw-alert__message').should('be.visible')
            .contains('Configuration has been saved.');
    });
});
```
For a reference to Cypress' commands and assertions, please see 
[Cypress API documentation](https://docs.cypress.io/api/api/table-of-contents.html).

Did you notice those `cy.route` and `cy.server` lines? It's a way to wait for API responses in Cypress, giving us a 
more reliable way of waiting for the configuration to be saved. 

```javascript
cy.server(); // Start a server to begin routing responses
cy.route({ // Manage the behavior of network requests
    url: '/api/v3/_action/system-config/batch', 
    method: 'post' // Route POST requests with given URL
}).as('saveData'); // Save the request as alias to use it later
```

The alias `saveData` can be used later in the test: You can pass it to `cy.wait` that forces Cypress to wait until 
it sees a response for the request that matches. 

For this case you would use that as following: If the request with the alias `@saveData` gives a response, it gets the 
notification, waits for it to be visible and check its text.
```javascript
cy.wait('@saveData').then(() => {
    cy.get('.sw-notifications__notification--0 .sw-alert__message').should('be.visible')
        .contains('Configuration has been saved.');
});
```

Please refer to [Cypress network requests](https://docs.cypress.io/guides/guides/network-requests.html) and 
[Aliases](https://docs.cypress.io/guides/core-concepts/variables-and-aliases.html) for further detail.

### Hooks
Awesome, we wrote our first test! However ... It will fail right at the start, as we need to be logged in to use the 
Administration. So we need to do that, but our test should neither have dependencies on other tests nor should it test
more than our one configuration workflow!

Cypress got you covered by providing 
[hooks](https://docs.cypress.io/guides/core-concepts/writing-and-organizing-tests.html#Hooks), again similar to Mocha. 
These can be used to set conditions that you want to run before / after a set of tests or each test.

For this case you can log in to the Administration in the `beforeEach` hook of your `spec` file:
```javascript
    before(() => {
        cy.loginViaApi();
    });
```

You need to implement way to to log in on your own. In Cypress, test should use the UI only for the workflow to test.
This means, we have to use shortcuts for all other operations. In Shopware 6 we use the API to log in silently, so
feel free to see `loginViaApi` custom command as example, found in 
`platform/src/Administration/Resources/e2e/cypress/support/commands/api-commands.js`. 

If you want to use the same page as starting point of your test, it might be useful to add the `cy.visit` command to
the `beforeEach` hook as well.

Finally, our test spec should be ready to run! Let's look at it in completion:
```javascript
/// <reference types="Cypress" />

describe('PluginCypressTests: Test configuration', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.visit('/admin#/sw/plugin/index/list');
            });
    });

    it('edit plugin\'s configuration', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v3/_action/system-config/batch',
            method: 'post'
        }).as('saveData');

        // Open plugin configuration
        cy.get('.sw-data-grid__row--0 .sw-plugin-table-entry__title')
            .contains('Label for the plugin PluginCypressTests');

        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-context-button__button').click({force: true});
        cy.get('.sw-context-menu').should('be.visible');
        cy.contains('Config').click();
        cy.get('.sw-context-menu').should('not.exist');

        // Edit configuration and save
        cy.get('input[name="PluginCypressTests.config.example"]').type('Typed using an E2E test');
        cy.get('.sw-plugin-config__save-action').click();

        cy.wait('@saveData').then(() => {
            cy.get('.sw-notifications__notification--0 .sw-alert__message').should('be.visible')
                .contains('Configuration has been saved.');
        });
    });
});
```

## Executing the tests

There are several ways to run Cypress tests: You can use the  
[test runner](https://docs.cypress.io/guides/core-concepts/test-runner.html) or
[command line](https://docs.cypress.io/guides/guides/command-line.html). For example, if you want
to open the test runner via command line, please run the following command:
```bash
npm run open
```

Make sure the `--project` and `--env projectRoot` path in the command actually fits and customise it accordingly, 
if necessary.

Happy testing!

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-plugin-cypress-tests).
