# Shopware Acceptance Test Suite

## Introduction

The test suite is build with **Playwright**. For detailed information have a look into the [official documentation](https://playwright.dev/docs/intro).

## Setup

Navigate to this directory if you haven't yet.

```
cd tests/acceptance
```

Install the project dependencies.

```
npm install
```

Install Playwright.

```
npx playwright install
npx playwright install-deps
```

Make sure to add the required environment variables to your `.env` file in the acceptance directory (not the shopware root).

```
APP_URL="<shop base url>"
# optional with default dev setup
SHOPWARE_ACCESS_KEY_ID="<your-api-client-id>"
SHOPWARE_SECRET_ACCESS_KEY="<your-api-secret>"
```

To generate the access key you can use the following symfony command:

```
bin/console integration:create AcceptanceTest --admin
```

## Running Tests

Running all tests

```
npx playwright test
```

Running tests with UI mode

```
npx playwright test --ui
```

Running a single test file

```
npx playwright test product.spec.ts
```

Running tests with a specific tag.  
Documentation: [Tagging tests in Playwright](https://playwright.dev/docs/test-annotations#tag-tests)

```
npx playwright test --grep @checkout
```

Debugging tests

```
npx playwright test --debug
```

Reduce worker count

```
npx playwright test --workers 4
```

### Running with admin watcher

You can point playwright to a different admin url by setting `ADMIN_URL`. The default is `${APP_URL}admin/`.

```
ADMIN_URL=http://localhost:8080
```

## Debugging with Traces
Debugging failing tests within the pipeline is very easy now. Simply open the failed job within the pipeline of your merge request. In the right sidebar you will find a section called "Job artifacts". Click on the "Browse" button and navigate to the artifacts of the test you want to debug. Download the `trace.zip` file. You can open the trace in your local Playwright UI mode or simply go to [trace.playwright.dev](https://trace.playwright.dev/) and drop your trace there to get a debug mode within your browser.

## Test Strategy

The acceptance test suite is an essential part of the overall test strategy of Shopware. Within this test suite we will especially focus on user-centric testing to validate specific business requirements. This is performed in the fashion of end-to-end tests to cover the whole application within each scenario.

The suite uses the Playwright framework which can perform tests and assertions within different browser contexts, but also API contexts. It has a nice and clean test structure and uses human-readable language in its methods which makes it easier to understand a test case, even for non-technical people. The test automation is also fast and reliable.

### What is "end-to-end" acceptance testing?

With end-to-end we describe a cross-cutting test behaviour that cover several modules of the software at once to create more complex test scenarios. This can mean:

1. Covering all technical layers of the software from backend to frontend.
2. Covering all steps of a user journey from start to finish.

### Objectives

Because of its complexity, end-to-end testing can have different challenges, like longer runtime and the danger of flaky tests. Therefore, it is essential to know when to use it and how to use it in the correct manner. To have a clear focus on which test cases should be covered to what degree, we define prioritized objectives that give orientation for choosing the right test strategy.

#### 1. Ensure the most business-critical user workflows, which are:

-   Workflows related to the core business of the merchant, where issues would create potential threat of loosing money.  
    **Examples**: checkout, payment, order management, inventory management.
-   Security related aspects of the software, where issues would lead to a security breach, information leak or data loss.  
    **Examples**: login, user access privileges, account information, session handling.
-   Workflows which are relevant for the operation of the software itself, where issues would prevent the general usage of the software.  
    **Examples**: installation, updates.

#### 2. Ensure user features that are essential for the product strategy of Shopware, which are:

-   Important differentiator features to set Shopware apart from its competitors.  
    **Examples**: Shopping Experiences, Flow Builder
-   Features essential for choosing a paid plan, especially higher tier plans.  
    **Examples**: B2B components.

#### 3. Ensure user workflows that guarantee the smooth operation of an online store:

-   User workflows essential for the buying experience of the shop customer.  
    **Examples:** product search, product filtering.

There will be no clear definition on how many tests are required per priority, because it mostly depends on the use case. But, these priorities should help you to decide if you should cover your changes via end-to-end acceptance tests at all and to what extent you should cover them.

### When to use end-to-end acceptance testing

Besides the priorities, it is important to follow these additional rules about when to use end-to-end acceptance testing and when not.

#### 1. Testing on behalf of the user.

Most end-to-end acceptance tests originate from a manuel test case. These are often more complex tests and always put testing in behalf of the user first.

#### 2. Testing business requirements not regressions.

The end-to-end test should primarily be used for validating business requirements and not for checking regressions within the code.

#### 3. Not covered by other functional tests.

You should only use end-to-end tests if the functionality cannot be covered by other functional tests, like unit or integration tests. An end-to-end test often comes with longer execution times and should therefore be used wisely.

**Note:** Keeping the rules in mind, validating business requirements via the Storefront interface makes much more sense than the Administration. The Administration of Shopware is just a user interface to the Admin API and mostly makes use of simple CRUD operations. As the API and data layer of Shopware are already covered by many other functional tests, an end-to-end test navigating the Administration will therefore primarily focus on the user interface. In addition, there are other types of functional tests to cover the Administration, for example, component unit tests with Jest.

### Best Practices

If you decided that you want to create a new end-to-end acceptance test scenario, you should implement the following best practices.

#### Prevent repetition

As end-to-end test scenarios are complex and need a lot of resources, we should ensure to only cover the parts essential for validating the specific test case. Especially you should avoid testing parts which are already covered by existing tests. If there is the need to reuse specific parts for you test, make sure to use proper abstraction.

#### Use abstraction

Using abstraction is a nice way to reduce repetition and separating behaviour from testing requirements. In our test suite we use a lightweight [actor pattern](#the-actor-pattern) that extracts logic into different methods like pages, tasks, and data fixtures. Using this kind of abstraction has several advantages, like having clean and readable test scenarios, but also extracting behaviour into reusable test steps. This will not only leave out unnecessary code logic out of your test, but will also make it easier to adjust all effected tests if the behaviour of the application changes.

Playwright already comes with great options to make use of abstraction. Have a look at the official documentation of how to use [fixtures](https://playwright.dev/docs/test-fixtures) and [page objects](https://playwright.dev/docs/pom).

#### Use ubiquitous language

With every test scenario our goal is to create a well-structured and comprehensible test that could easily be understood by non-technical people. Therefore, you should use [ubiquitous language](https://martinfowler.com/bliki/UbiquitousLanguage.html) in all aspects of the test, like descriptions, assertions, abstraction methods etc.

We achieve this by using a lightweight actor pattern on top of Playwright which enables us to write test scenarios in a readable way, that non-tech people are able to understand. We describe the concept in more detail in the following section.

### The Actor Pattern

With the actor pattern we create tests in a readable language that follows a user-centric approach where a specific user / persona, called actor, performs several specific actions, called tasks. In addition, we use other types of artifacts that help to extract the actual test logic and make it reusable. There are these different artifacts that we use to simplify the test scenario:

-   **Actor**: a specific user persona with a given context. For example a shop customer or an administrator.
-   **Task**: a specific action performed by an actor. For example adding a product to the cart.
-   **Page Object**: a helper class that resembles possible interactions with a user interface.
-   **Data Fixture**: a helper that creates necessary test data via API to put the system under test into the right state for the test scenario.

This pattern enables us to create test scenarios that look like this:

```JavaScript
await shopCustomer.goesTo(checkoutCartPage);
await shopCustomer.attemptsTo(AddPromotionCodeToCart(promotionName, promotionCode));
await shopCustomer.expects(checkoutConfirmPage.grandTotalPrice).toHaveText('€90.00*');
```

You can see that the scenario of adding a promotion code to the cart can be defined by just three simple steps that are written in a way which also non-technical people would comprehend. This is possible due to the actor pattern which uses an object-oriented way to perform actions and assert results. It is also complemented by the syntax of Playwright itself, as its methods already make use of quite a ubiquitous language. You can see the following patterns:

-   **Actor** *goes to* a **page**.
-   **Actor** *attempts to* perform a **task**.
-   **Actor** *expects* a certain result.

Every artifact can easily be accessed in each test scenario via the dependency injection of Playwright. That is, because every artifact is provided via Playwright [fixtures](https://playwright.dev/docs/test-fixtures). To create a full test with the example from above that makes use of an actor, task, page object and data fixture could look like this:

```JavaScript
import { test } from '@fixtures/AcceptanceTest';

test('Registered shop customer uses a promotion code during checkout.', async ({
    shopCustomer,
    checkoutCartPage,
    promotionWithCodeData,
    AddPromotionCodeToCart
}) => {
    const promotionCode = promotionWithCodeData.promotionCode;
    const promotionName = promotionWithCodeData.promotionName;

    await shopCustomer.goesTo(checkoutCartPage);
    await shopCustomer.attemptsTo(AddPromotionCodeToCart(promotionName, promotionCode));
    await shopCustomer.expects(checkoutCartPage.grandTotalPrice).toHaveText('€90.00*');
});
```

**Note:** It is important to import the test method from our main fixture class `'@fixtures/AcceptanceTest'` to have access to all Shopware-specific fixtures. You can import all Playwright specific methods from there as it is used as a wrapper for Playwright, just as Playwright recommends it within their [fixture documentation](https://playwright.dev/docs/test-fixtures).

#### Tasks
The tasks are a central part of adding behaviour logic to your test scenarios. They contain the most test logic. The advantage of abstracting the logic in smaller tasks is, that you can reuse them in different scenarios. If the behaviour of a certain task changes, you just have to fix the corresponding task fixture and all scenarios that make use of this specific task will automatically be adjusted.

To make the tasks available via Playwright fixtures it is important that they are created in a certain way. Otherwise, you couldn't use them via the dependency injection of your test, and they would not fit into the language of the actor pattern. To make it easier for creating new tasks in the right way, there is a npx command that you can use to simply create a new file with the necessary boilerplate code. The files for the tasks are put into different directories to structure them by actors and domains. You can specify the directory structure within the command.

Command:  
```
npx createTask <actor>/<domain>/<task>
```

Example:  
```
npx createTask ShopCustomer/Checkout/SubmitOrder
```

The command will create the necessary directory structure, if it is not an existing directory, and also create the file within the directory. Please remember, that you still have to check in the file to the Git version control and also import it in the corresponding main fixture file that collects all the fixtures. For example, the command above will create the following file.

```TypeScript
import { test as base } from '@playwright/test';

export const SubmitOrder = base.extend({
    SubmitOrder: async ({ shopCustomer }, use)=> {
        const task = () => {
            return async function SubmitOrder() {

                // Add your test content here
                
            }
        };

        use(task);
    },
});
```

Within the task file you can just use simple Playwright to create the test logic. You can also make use of other fixtures via the dependency injection like the `shopCustomer` parameter in the example. Your task can also request optional parameters within the task function that should be passed by the test scenario.

```JavaScript
const task = (promotionName, promotionCode) => {
    return async function AddPromotionCodeToCart() {
        // Use promotionName and promotionCode in your task logic
    }
};
```

Use your task within the test scenario with the `attemptsTo()` method of the corresponding actor class.

```JavaScript
await shopCustomer.attemptsTo(AddPromotionCodeToCart(promotionName, promotionCode));
```

This will execute the test code of the task. In addition, it will automatically wrap the execution in a Playwright test step, that will use the actor pattern to add meaningful description to the generated report of the test suite. When debugging your tests you can easily identify in which task an issue occurred.
