In this guide, you will get to know all important details on end to end (e2e) testing in Shopware. 
These e2e tests are used in order to test the ui and ensure that the main functionalities of Shopware are always working correctly.

## Basics 

### Nightwatch.js

In the e2e tests of Shopware platform, the testing framework `Nightwatch.js` is used which is written on Node.js. It uses the W3C WebDriver API to work with the browser, 
which means performing commands and assertions on DOM elements. You can find detailed documentation about how to write Nightwatch e2e tests in their documentation: 
<http://nightwatchjs.org/>

### Repo structure

The e2e test suite is structured as a mono repository for Nightwatch tests. The corresponding files are divided into separate sections, henceforth referred to as repositories.

```
e2e
├── common // Files which are shared throughout the repos
│   ├── @fixtures
│   ├── custom-commands
│   ├── helper
│   └── service
│       └── fixture
├── node_modules
├── repos
│   ├── administration // repo for administration-based tests
│   │   ├── custom-commands
│   │   ├── page-objects
│   │   │   └── module
│   │   ├── specs // Files which contain the actual test suites
│   │   │   ├── admin-menu
│   │   │   ├── customer
    ...
│   │   │   └── settings
│   │   │       └── tax
│   │   └── @types
│   └── storefront // repo for the storefront-based tests 
│   │   ├── custom-commands
│   │   ├── page-objects
│   │   ├── specs // Files which contain the actual test suites
│   │   │   └── homepage
│   │   └── @types
└── temp // Folder for temporary files, e.g. your database dump
```
*Folder structure*

Every repository can have its own config, including its own globals-file, custom commands and spec-folders. However, the repositories' general structure should be the same for each of them, as far as possible: 
For example, the repository "Storefront" should use the same folder as specified in the "administration" repo.

### Spec files: Test suite structure

A file with the suffix "*.spec.js" is a test suite that contains a sequence of tests, performed in the order defined in it. You can see an example below:

```javascript
module.exports = {
    tags: ['testsuite', '<module-name>', ...]   // separates testsuites
    before: (browser, done) {
        // navigates to the correct url, checks if the url is correct
        // using the api client or fixture generator to provide the necessary information for the test
    },
    '<description-of-the-test>': (browser) {
        // ... implementation of the test step
    },
    after: (browser) {
        // closes the browser session
        // resets the project
    }
};
```
*Test suite as whole construct that contains the separate tests*

At the beginning of the test suite, you can define tags applicable to this very test suite. these tags are used to run either a single test or a special group of test suites at the same time. This is especially useful for debugging or a parallel execution of different test suites.

```javascript
'@tags': ['section', 'single-test-referring-tag', 'under-section', 'action', 'whatever-other-tag-you-might-find-useful'],
```
*Example for tags*

A simple test looks like this:
```javascript
},
'do one action': (browser) => {
    browser
        .waitForElementVisible('.sw-dashboard-index__content');
},
```
*One single test*

Important: A single test should correlate with a single action in a user's workflow. An action in this context is e.g. filling in a form, not just filling in one input field. 
Another example would be a `verify ceration` step, in which various values are checked in order to ensure that the input data was inherited correctly.  

### CLI Commands

Here, you can find a brief overview of e2e commands provided by `psh.phar`. Most can be found in the folder `dev-ops/e2e` of the development template, in folder `/actions` to be exact.

* `./psh.phar e2e:dump-db`: This command can be used to dump Shopware's database.
* `./psh.phar e2e:init`: This command should be used first. It runs `.psh.phar init` and `./psh.phar administration:build` to set up the environment and install all necessary dependencies afterwards.
* `./psh.phar e2e:restore-db `: This command restores your database dump.
* `./psh.phar e2e:run`: This is the command you will use most of the time. It dumps the plugins and database of your installation, and runs the tests afterwards.

Important: Please note that the test suite is independent from the administration and brings its own `package.json`, including own dependencies. 
You have to install them before using the e2e tests.

The command `e2e:run` provides the possibility to use various parameters:

* `--NIGHTWATCH_PARAMS="--tag wonderful-tag"`: This way you can use Nightwatch.js-parameters, e.g. `--tag` for using tags.
* `--NIGHTWATCH_ENV="storefront"`: The repository `administration` is configured as default environment. With the usage of this command, you can set any other repository as environment whose test will be executed.
* `--NIGHTWATCH_HEADLESS=false`: This command toggles the headless-mode of the Nightwatch tests. This way, you are able to run the tests non-headless in local development.
* `--APP_WATCH=true`: This will provide you to launch the tests on the URL of the `administration:watch` environment if set to true. By default it's set to false so that the test launch on default port 8000.

## Test hooks in detail

It's important and necessary that the e2e tests are isolated: That means that the test should create all the data needed by itself for running a test beforehand. 
Afterwards, all changes in the application must be removed completely. This way, the spec avoids dependencies to demo data or data from other test and cannot be disturbed by those. 
One test suite should only test one workflow, the one it's written for. For example, if you want to test the creation of products, you don't want to include the creation of categories 
in your test, although its creation is needed to test the product completely. So you include the creation of categories, before the test starts to run.

In Shopware, we implemented these set up and clean up steps via the lifecycle hooks Nightwatch provides: <http://nightwatchjs.org/guide#using-before-each-and-after-each-hooks>

### beforeEach-hook: Logging in and doing stuff beforehand

In addition to those routines, there are other things which have to be done beforehand, like logging in or closing the symfony toolbar if needed. These beforeEach-hook can be found 
in `/e2e/repos/administration/globals.js`. The steps in it will be executed before every test suite:

```javascript
beforeEach: (browser, done) => {
    // Set the launch url, where the tests will start from 
    browser.url(browser.launch_url);

    browser.execute(function () {
        // Disable the auto closing of notifications globally.
        Shopware.State.getStore('notification')._defaults.autoClose = false;

        // Return bearer token
        return localStorage.getItem('bearerAuth');
    }, [], (data) => {
        // Do all login steps
        if (!data.value) {
            beforeScenarioActions.login(browser, 'admin', 'shopware', done);
        }
        
        // Collapse the symfony toolbar in case of it being expanded
        beforeScenarioActions.hideToolbarIfVisible(browser);
        done();
    });
}
```
*beforeEach-hook with the included steps*

Important: If you use this hook in your globals file, the `beforeEach` hook will be applied to every test suite, but not every test step: 
Execution in every test step will occur, if you use the hook in one test suite respectively.

### before-hooks with fixtures

The 'before'-hook attached to each test suite is of utmost importance in shopware's e2e tests. Before-hooks will be executed, as the name implies, right before the test suite in 
itself is performed. This hook takes care about setting fixtures for the test: All necessary test data can be created before the test suite start to run. In Shopware, 
these fixtures are created or updated using the Shopware API.

```javascript
before: (browser, done) => {
    global.FixtureService.create('api-endpoint').then(() => {
        done();
    });
}   
```
*before-hook with use of the basic FixtureService*

To cover this topic in detail, we wrote a separate [guide](../70-testing/30-working-with-e2e-fixture-services.md).

### afterEach-hook and cleanup

Just like the `beforeEach` hook, the `afterEach`-hook is located in `/e2e/repos/administration/globals.js`. It will in contrast be executed after each test suite and cleans up 
the test data created at the beginning and in the process of the test suite. 
By default, the database dump set in `e2e:run` will be restored which allows Shopware to return to its initial state. 

```javascript
afterEach(client, done) {
    console.log();
    console.log("### Resetting database to clean state...");
    exec(`${process.env.PROJECT_ROOT}psh.phar e2e:restore-db`).then(() => {
        global.logger.log('success', 'Successful');
        done();
    }).catch((err) => {
        global.logger.log('error', err);
    });
}
```
*after-hook with clean up routine*

### after-hook in each spec

The after-Hook will be executed after the test it was written in. In most cases, it only contains the `browser.end();` method which ensures that the browser session can be ended correctly.

```javascript
after: (browser) => {
    browser.end();
}
```
*Simple after-Hook*

## Custom commands

Most of the time you will need to extend the Nightwatch commands to suit your own application needs. For Shopware's e2e tests, we did the same. Below you can read about the custom 
commands we created until now. For further information about how to write those commands, please refer to <http://nightwatchjs.org/guide#writing-custom-commands>.

| Command      | Parameter | Description|
| ----------- | ----------- | ----------- |
| checkIfElementExists      | (selector, callback = () => {})       | Checks if an element is existent, without causing the test to fail|
| checkNotification   | (message, toBeClosed = true, type = '.sw-alert')        | Checks the notification and its message: Checks if a notification prints out the message the user expects to get. Afterwards the notification can be closed, if required|
| clearField  | (selector, type = 'input')        | Clears an input field and making sure that the field is in fact empty afterwards|
| clickContextMenuItem   | (menuButtonSelector, menuOpenSelector, scope = null)        | Opens and clicks a context menu item, even if it's in a specific scope|
| clickUserActionMenu  | (name, open = true)        | Opens or collapses the user-related menu section of the admin menu, containing language switch, profile and logout|
| fillField   | (selector, value, clearField = false, type = 'input')        |Finds a form field in the Administration using the provided css selector. It tries to find the element on the page, clears the value (if configured) and sets the provided value in the field.|
| fillGlobalSearchField   | (value, clearField = false)        |Uses the global search input field in the Administration for finding a product or other entity.|
| fillSelectField   | (selector, value)        | Finds a form field in the Administration using the provided label. The method uses a CSS selector to find the element on the page, clears the value (if configured) and sets the provided value in the field. |
| fillSwSelectComponent   | (value, clearField = false)        |Uses the global search input field in the Administration for finding a product or other entity.|
| openMainMenuEntry   | (openMainMenuEntryOptions)       | Finds and opens a main menu entry in the Shopware Administration menu. It is possible to provide a sub menu item name to open sub menu entries.|
| tickCheckbox   | (selector, value)        | Finds a form field in the Administration using the provided selector. The method uses that selector to find the element on the page and ticks it. |

## Page Objects

To represent our modules best, we orientate our tests towards the page object pattern. Nightwatch.js describe their view of it here: <http://nightwatchjs.org/guide#page-objects>

However, we do not use Nightwatch's page objects: In Shopware's case, those page objects are a collection of selectors, functions and helper functions that are tailor-made for every 
single module. Commonly used selectors or test steps can be found easily, so you can focus on the actual workflow of your test.

In order to fit our needs at Shopware better, we applied a different setup from that one described in the official Nightwatch documentation: For the reason to abstract commonly used steps 
in a page while keeping it maintainable, we write page objects as own classes which will be exported directly afterwards. This way, a page object can look like this:

```javascript
class OurOwnPageObject {
    constructor(browser) {
        this.browser = browser;

        // Definition of elements which can be found in the module often
        this.elements = {
            columnName = 'sw-product-list__column-product-name'
        };
    }

// Here, commonly used test steps are abstracted in methods
    oneSimpleTestStep(name) {
        this.browser
            .setValue('input[name=sw-field--product-name]', name)
            .click('.sw-product-detail__save-action')
            .checkNotification(`Product "${name}" has been saved successfully`);
    }
}

module.exports = (browser) => {
    return new OurOwnPageObject(browser);
};
```

In the constructor of the PageObject class, the page elements are defined. These elements are selectors commonly found on a variety of pages. As a consequence, 
they will be used in a lot of test suites. By moving them to a pageObject, these selectors are accessible from every single test suite.

Afterwards, outside the constructor, the methods can be defined. These methods should contain test steps you use a lot in your modules' tests. This way, they are accessible in all your 
tests.

## Best practises and guidelines

- The xPath selector strategy relies mainly on texts that might be subject to change quite often. That is why you should refrain from using xPath as selector strategy, as often as possible. Please avoid the usage of xPath as selector strategy, as far as possible.
- If you notice that a selector is missing for the element you need to find, or if it's too general, please create an additional one accordingly by adding a class definition to the 
code yourself. Make sure you use BEM naming scheme and name it descriptively.
- Never use fixed timeouts, e.g. `.pause(5000)`.
    - You can wait for changes in the ui, e.g. if a notification shows up or a loading indicator disappears.
    - You don't need to define the dynamic timeout duration, as it's already defined in `globals.js`.
- After every create, please ensure that the entity was in fact created. If a redirect is expected, e.g. when creating a product make sure it takes place as expected.
- Please use descriptive names for the tests. E.g. if an inline editing is used in your test, please specify it in the file name. Preferably, use the module you are working with as a prefix 
when naming your test and further specify it by adding concrete test tasks as well. An example file name can look like this: `product-create.spec.js`

## Some tricks

### Using non-headless mode

If you're working on your local machine, you can run the e2e tests in a non-headless mode. That way, the browser will run your tests directly on your machine, for you to inspect and to 
see what the browser is actually doing. You can enable this possibility via adding the following parameter:

```bash
./psh.phar e2e:run --NIGHTWATCH_HEADLESS=false 
``` 

If you prefer and work on a docker setup, it is still possible to use the non-headless mode. Just bear in mind using the headless-parameter in your container will have no use. 
Instead, please use the command from outside your container, in the `development` folder.
 
Attention! To make this work in a split environment (docker and local development), you need to add the following lines to your `psh.yaml.override` in order to ensure a proper 
usage of the defined ports and hosts:

```bash
dynamic:
  DB_HOST: if [ -z "$(grep docker /proc/self/cgroup)" ]; then ip -4 addr show enp14s0u1u2 | grep -oP '(?<=inet\s)\d+(\.\d+){3}'; else echo "app_mysql"; fi
  DB_PORT: if [ -z "$(grep docker /proc/self/cgroup)" ]; then echo 4406; else echo 3306; fi
```

Important: Please keep in mind that the designation `enp14s0u1u2` is dependent on your network configuration, so make sure to keep network connection. You might need to adjust this 
information according to your local setup, but please note that this exact syntax will not work on macOS. You might set your ip address instead of the designation instead.

### Screenshots in e2e tests

Nightwatch automatically makes and stores screenshots of your application. Screenshots are made at the very moment a test fails. When a test step has failed, you can access these 
screenshots in the folder `build/artifacts/e2e/screenshots` of the development template.

### Launch e2e tests on administration:watch

To avoid having to build the administration anew at every small incremental change, you may want to launch e2e tests on a port `./psh.phar administration:watch` is running on. 
As this environment is compiled alongside every code change, these changes will be noticed by the tests as well.

To launch test in the way described, use the `APP_WATCH=true` parameter when executing a test via `e2e:run`. By default, this parameter is set to "false", causing your tests to be 
launched as usual, on port 8000.

```bash
./psh.phar e2e:run --NIGHTWATCH_PARAMS="--tag media-replace" --APP_WATCH=true
```

The port of the tests' launch-urls will be replaced according to the definitions at `DEVPORT` in the `.psh.yaml.dist`.

### Set own cli output

In the `common/helper/`-folder, a service called `cliOutputHelper.js`can be found. This HelperClass provides you the possibility to
set customised log entries in your tests. By now, you can use following log messages:

```javascript
global.logger.title('Title'); // ### Title
global.logger.success('It was successful!'); // • ✓ It was successful!
global.logger.error('Oops, something went wrong.'); // • ✖ Oops, something went wrong.
global.logger.log('Some boring information'); // Some boring information
```
