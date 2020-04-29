[titleEn]: <>(End-to-end testing)
[hash]: <>(category:e2e_testing)

In end-to-end testing (E2E testing) real user workflows are simulated, whereby as many as possible functional areas and 
parts of the technology stack used in the application should be included.
This way, we are able to put our UI under constant stress and ensure that Shopware's main functionalities
are always working correctly. As we are using Cypress as testing framework, please take a look at 
<https://docs.cypress.io/> for further documentation.

## E2E testing with Cypress

The [E2E platform testsuite package](https://github.com/shopware/e2e-testsuite-platform) contains all you need to 
build E2E tests for Shopware 6. This test suite is built on top of [Cypress](https://www.cypress.io/) as well as 
the following Cypress plugins:
                                
* [cypress-select-tests](https://github.com/bahmutov/cypress-select-tests)
* [cypress-log-to-output](https://github.com/flotwig/cypress-log-to-output)
* [cypress-file-upload](https://github.com/abramenal/cypress-file-upload)

On top of that, test data management and custom commands are included as well. More on that [here](#Commands). 
But first, let's get your E2E tests [up and running](#Installation-and-requirements). 

In general, the following topics are covered here:
