[titleEn]: <>(Cypress as E2E testing framework)

As from today, we made the shift from Nightwatch.js to [Cypress](https://www.cypress.io/) as our primary E2E testing 
framework. Cypress provides lots of features aiding us keep the quality high:

* More features concerning debuggability, e.g. taking snapshots as the tests run 
* Automatic waits for commands, assertions and animations
* Possibility to run tests in parallel
* Spies, stubs, and clocks for controlling the behavior of functions, server responses, or timers

The existing Nightwatch tests have already been migrated to Cypress. Consequently, the `e2e` project structure has been
changed distinctly:

* In general, Cypress e2e tests can be found in `Administration/Resources/e2e/cypress/integration`
* We separated administration and storefront test in order to move them to their corresponding repository, 
so you need to change the path accordingly: For example, please use `Storefront/Resources/e2e/cypress/integration` to 
run storefront-related tests
* You can find the test files in `Administration/Resources/e2e/cypress/integration`
* Configuration can be done in `cypress.json` and `cypress.env.json` for environment-related configuration

##  Running tests

If you use docker, Cypress is shipped in an own container. In this case, please keep in mind that you need to run Cypress 
commands from your local machine, as you cannot run docker commands in docker containers.

At first, you need to set up your environment for running E2E tests:
```bash
./psh.phar e2e:init
```

Afterwards, you should be able to run the test suite in command line using the following command:
```bash
./psh.phar e2e:run
```

Of course you can still use own parameters, as you did using Nightwatch:
* Similar to usual Nightwatch usage, you can add own parameter via `--CYPRESS_PARAMS`
* By default, Administration tests will be selected. If you want to switch to the storefront tests, 
please add `--CYPRESS_ENV="storefront`to your command

### Using the test runner

A main feature is Cypress' test runner which provides us a bunch of features to help with debugging tests. To 
start the test runner, you simply use the following command:
```bash
./psh.phar e2e:open
```
Please keep in mind that your operating system needs a graphical interface to run the test runner. This way, you need
to install Cypress locally if you want to use it, even if you use docker for everything else. 

### Running selected tests

You may only want to run one or more selected tests. For this you can use the following parameters:
* If you want to run selected spec files, use `--spec path/to/file.spec.js`
* If you want to use Mocha-like selection, user ./psh.phar e2e:run --CYPRESS_PARAMS="--env grep=yourSearchTerm"  
    * e.g.`@p` to select only tests that are relevant for package testing, you can use 
    `./psh.phar e2e:run --CYPRESS_PARAMS="--env grep=@p"`
    * For selecting a single test, you need to use a unique string from the tests' title 
    `--CYPRESS_PARAMS="--env grep='delete sales channel'` in your parameters

## Documentation

* [Cypress documentation in general](https://docs.cypress.io/)
* [API documentation](https://docs.cypress.io/https://docs.cypress.io/api/api/table-of-contents.html)
* [Examples, guides and more](https://docs.cypress.io/examples/examples/recipes.html)
