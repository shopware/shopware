[titleEn]: <>(Writing End-to-End Tests)
[hash]: <>(article:e2e_testing_writing)

In this article you will get to know all information necessary to write your first E2E test.

Please keep in mind that we will not cover how to write a Cypress tests in general.
Consider having a look at the [Cypress docs](https://docs.cypress.io) to get to know Cypress in depth.

## Folder structure

In Shopware platform, you can find the tests in `src/Administration/Resources/app/administration/test/e2e`.
There you can find the following folder structure, depending on your environment being Administration or Storefront:
```javascript
`-- e2e
  `-- cypress
    |-- fixtures
        `-- example.json
    |-- integration
        `-- testfile.spec.js
    |-- plugins
        `-- index.js
    |-- support
        |-- commands.js
        `-- index.js
    |--cypress.json
    `--cypress.env.json
```

In the `cypress` folder, all test related folders are located. Most things will take place in these four folders:
* `fixtures`: Fixtures are used as external pieces of static data that can be used by your tests. You can use them
with the `cy.fixture` command.
* `integration`: By default, the test files are located here. A file with the suffix "*.spec.js" is a test file that
contains a sequence of tests, performed in the order defined in it.
* `plugins`: Contains extensions or plugins. By default Cypress will automatically include the plugins file before
every single spec file it runs.
* `support`: The support folder is a great place to put reusable behavior such as custom commands or global overrides in,
that you want to be applied and available to all of your spec files.

These two configuration files are important to mention as well:
* `cypress.json`
* `cypress.env.json`
These are Cypress configuration files. If you need more information about them, take a look at the 
[Cypress configuration docs](https://docs.cypress.io/guides/references/configuration.html).

If you need to use this structure in a plugin, it is just the path to the `e2e` folder, which is slightly different.
You can find the folder structure in the article [Installation and requirements](#Installation-and-requirements).

If you want to contribute to Shopware platform's tests, please ensure to place your test in one of those folders:
```javascript
`-- integration
  |-- catalogue
  |-- content
  |-- customer
  |-- general
  |-- media-marketing
  |-- order
  |-- rule-product-stream
  `-- settings
```

This is important because otherwise your test is not considered by our CI.

## Test layout and syntax

Cypress tests are written in Javascript. If you worked with Mocha before, you will be familiar with Cypress' test
layout. The test interface borrowed from Mocha provides `describe()`, `context()`, `it()` and `specify()`.

To have a frame surrounding your test and provide a nice way to keep your test organized, use `describe()` (or `context()` as its alias):
```javascript
describe('Test: This is my test file', () => {
    it('test something', () => {
        // This is your first test
    });
    it('tests something else', () => {
        // This is your second test
    });
});
```

The `it()` functions within the `describe()` function are your actual tests.
Similar to `describe()` and `context()`, `it()` is identical to `specify()`. However, for writing Shopware tests
we focus on `it()` to keep it consistent.

## Commands and assertions

In Cypress, you use commands and assertions to describe the workflow you want to test.

### Commands

Commands are the actions you need to do in order to interact with the elements of your application and reproduce the
workflow to test in the end.
```javascript
it('test something', () => {
    ...
    cy.get('.sw-grid__row--0')
        .contains('A Set Name Snippet')
        .dblclick();
    cy.get('.sw-grid__row--0 input')
        .clear()
        .type('Nordfriesisch')
        .click();
    ...
    });
    
```

You can chain commands by passing its return value to the next one. These commands may contain extra
steps to take, e.g. a `click` or `type` operation.

Cypress distinguishes between parent, child and dual commands, see [Commands](#Commands) article for details.

Cypress provides a lot of commands to represent a variety of steps a user could do. On top of that, our E2E testsuite
contains a couple of [custom commands](#Shopware's-custom-commands) specially for Shopware.
Of course, you can also write own custom commands as well. A more detailed insight in this topic can be found in the 
[Commands](#Commands) passage.

### Assertions

Assertions describe the desired state of your elements, objects and application. Cypress bundles the Chai
Assertion Library (including extensions for Sinon and jQuery) and supports both BDD (expect/should) and TDD (assert)
style assertions. For consistency reasons, we prefer BDD syntax in Shopware's tests.

```javascript
it('test something', () => {
    ...
    cy.get('.sw-loader')
        .should('not.exist')
        .should('be.visible')
        .should('not.have.css', 'display', 'none');
    cy.get('div')
        .should(($div) => {
            expect($div).to.have.length(1)
        });
    ...
    });
```

## Hooks

You might want to set conditions that you want to run before a set of tests or before each test. At Shopware we use 
those to e.g. clean up Shopware itself, login to the Administration or to set the admin language.

Cypress got you covered, similar to Mocha, by providing hooks. These can be used to set conditions that
you can run before or after a set of tests or each test.

```javascript
describe('We are using hooks', function() {
  before(function() {
    // runs once before all tests in the block
  })

  beforeEach(function() {
    // runs before each test in the block
  })

  afterEach(function() {
    // runs after each test in the block
  })

  after(function() {
    // runs once after all tests in the block
  })
})
```

### Build up and teardown

As we mentioned before, we use these hooks to build up the ideal situation for our test to run. This includes
cleaning up its previous states. According to Cypress'
[thoughts on anti-patterns](https://docs.cypress.io/guides/references/best-practices.html#Using-after-or-afterEach-hooks)
we clean up the previous state of Shopware beforehand. The reason is pretty simple: You can't be completely sure to reach 
the `after` hook (sometimes tests may fail), the safer way to cleanup your tests is the `beforeEach` hook. 
On top of stability advantages, it's possible to stop the tests anytime without manual cleanup.

## Guide to your first test and other examples

We wrote a [how-to guide](https://docs.shopware.com/en/shopware-platform-dev-en/how-to/end-to-end-tests-in-plugins)
on how to write your first test using Cypress. Of course, you can also refer to all tests we wrote for Shopware
platform before.
