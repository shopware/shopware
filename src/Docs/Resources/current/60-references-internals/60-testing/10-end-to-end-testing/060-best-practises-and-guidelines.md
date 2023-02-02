[titleEn]: <>(How do I write a good test?)
[hash]: <>(article:e2e_testing_guidelines)

A typical E2E test can be complex, with a large number of steps that take a lot of time to complete manually. 
Because of this complexity, E2E tests can be difficult to automate and slow to execute. The following tips 
can help reduce the cost and pain of E2E testing and still reap the benefits.

Cypress got you covered with their best practises as well: So please also look at their
[best practises](https://docs.cypress.io/guides/references/best-practices.html) to get to know their patterns.

## When should I consider writing an E2E test?

Due to running times it is not advisable to cover every single workflow available. That's why we need a way to 
prioritize our test cases. The following criteria may help you with that:

* Cover the most general and most used workflows of a feature, e.g. CRUD operations. The term "happy path" describes
those workflows quite well. 
* Use risk-analysis: Cover those workflows with E2E tests, which are most vulnerable and would cause most damage 
if broken. 
* Avoid duplicate coverage: E2E tests should only cover what they can cover, usually big-picture user 
stories (workflows) that contain many components and views. 
  * Sometimes unit tests are suited better: For example, use an E2E test to test your application's reaction on a 
  failed validation, not the validation itself.

## Structure and Scope

The second most important thing is to just test the workflow you explicitly want to test: Any other steps or workflows 
to get your test running should be done using API operations in the `beforeEach` hook, as we don't want to test 
them more than once.

For example: if you want to test the checkout process you shouldn't do all the steps like create the sales channel, products and categories 
although you need them in order to process the checkout. Use the API to create these things and let the test just do the checkout.

You need to focus on the workflow to be tested to ensure minimum test runtimes and to get a valid
result of your test case if it fails. Fot this workflow, you have to think like the end-user would do: 
Focus on usage of your feature, not technical implementation.

Other examples of steps or workflow to cut off the actual tests are:
* The routines which should only provide the data we need: Just use test fixtures to create this data 
to have everything available before the test starts.
* Logging in to the Administration: You need it in almost every Administration test, but writing it in all tests is pure redundancy 
and way more error sensitive.

This [scope practise](https://docs.cypress.io/guides/references/best-practices.html#Organizing-Tests-Logging-In-Controlling-State)
is also mentioned in Cypress' best practises as well.

## Focus on stability first

Don't ever rely on previous tests! You need to test specs in isolation in order to take control of your
application’s state. Every test is supposed to be able to run on its own and independent from any other tests.
This is crucial to ensure valid test results. You can realize this using test fixtures to create all data you need 
beforehand and taking care of the cleanup of your application using an appropriate reset method.

## Choosing selectors

XPath selectors are quite fuzzy and rely a lot on the texts, which can change quickly.
Please avoid using them as much as possible. If you work in Shopware platform and notice that one selector is missing
or not unique enough, just add another one in the form of an additional class.

## Waiting in E2E tests

Never use fixed waiting times in the form of `.wait(500)` or similar. Using Cypress, you never need to do this.
Cypress has a built-in retry-ability in almost every command, so you don't need to wait e.g. if an element already
exists. If you need more than that, we got you covered: Wait for changes in the UI instead, notification, API requests, 
etc. via the appropriate assertions. For example, if you need to wait for an element 
to be visible:
```javascript
cy.get('.sw-category-tree').should('be.visible');
```

Another useful way for waiting in the Administration is using Cypress' possibility to work with 
[network requests](https://docs.cypress.io/guides/guides/network-requests.html). Here you can let the test wait for a 
successful API response: 

```javascript
cy.server();

// Route POST requests with matching url and assign an alias to it
cy.route({
    url: '/api/v3/search/category',
    method: 'post'
}).as('getData');

// Later, you can use the alias to wait for the API response
cy.wait('@getData').then((xhr) => {
    expect(xhr).to.have.property('status', 200);
});
```
