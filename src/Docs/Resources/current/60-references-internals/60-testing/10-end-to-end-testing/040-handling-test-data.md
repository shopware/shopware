[titleEn]: <>(Test data handling)
[hash]: <>(article:e2e_testing_data)

It's important and necessary that the E2E tests are isolated. This means that the test should create all the data
needed for running by itself beforehand. Afterwards, all changes in the application must be removed completely.
This way, the spec avoids dependencies to demo data or data from other tests and cannot be disturbed by those.

One test should only test one workflow, the one it's written for. For example, if you want to test the creation
of products, you should not include the creation of categories in your test, although its creation is needed to
test the product properly. As best practise we recommend to handle everything not related to the test using the
[lifecycle hooks](https://docs.cypress.io/guides/core-concepts/writing-and-organizing-tests.html#Hooks)
Cypress provides.

In Shopware platform, we use Shopware's REST API to request and in response to create the data we need.
As a result, our tests are able to focus on one single workflow without having to test the routines which
should only provide the data we need. Another aspect of handling it this way is, that creating test data via API is faster than doing it inside the test.

## Cypress' fixtures

To define the request you send to Shopware and to set first test data, store your information in the folder
`e2e/cypress/fixtures`. The files in this folder use the json format to provide the structure and data Shopware
needs to create the fixtures.

To give an example, please look at the file `customer.json`:

```json
{
  "customerNumber": "C-1232123",
  "salutation": "Mr",
  "firstName": "Pep",
  "lastName": "Eroni",
  "email": "test@example.com",
  "guest": true,
  "addresses": [
    {
        ...
    }
  ]
}
```

As the name implies, this file is used in order to define and create a customer. Furthermore, it already provides data
so that the customer can be created in Shopware. As a result, you can use those files to provide fixed test data
which can be used directly to create the desired entity without any further searching or processing.

Fortunately, Cypress provides a way to handle those fixtures by default. The command `cy.fixture()` loads this fixed set
of data located in a json file.

Note: Use only fields, which you can access in the UI / Storefront. Also your complete test should work with this data. 
Using ids may be easier for finding, but it isn't a proper way for testing. Never use ids here if you cannot be 
100% sure that they will not change at all, e.g. in another build. 

## API implementation

Analogue to the administration itself, the api access of the e2e test is based on
[axios](https://www.npmjs.com/package/axios), a promise based HTTP client for the browser and node.js.

Just like the administration, we use services to access Shopware's REST API. Therefore we use the ApiService
to provide the basic methods for accessing the api. Located in `e2e/cypress/support/service/api.service.js`,
ApiService is shared between all repositories and acts as a basis for all your next steps of creating fixtures.
That implies that the axios implementation of all important api methods can be found there.
This service acts as an interface: Next to the basic functions like get, post etc the request method is specified
here as well as some Shopware-related methods which have to be available in all repositories.

Important: Cypress provides an own axios-based way to handle requests in its command `cy.request`. However, Cypress
commands are not real promises, see
[Commands are not Promises](https://docs.cypress.io/guides/core-concepts/introduction-to-cypress.html#Commands-Are-Not-Promises).
As we aim to parallelize the promises to fetch test data, we use our own implementation instead.

## Services and commands

In order to get all test fixture data applied to our Shopware installation, we use services to send the API requests
to find, create or update the data we need. To access these services in a convenient way, we provide custom commands,
which we'll cover a bit later. Let's continue with the general things first.

All fixture services can be found in `cypress/support/service/`:
```bash
service
  |-- administration // this folder stores the administration channel API services
    `-- <environment>
      `-- test
        `-- e2e
          `-- cypress
            |-- fixture
            |-- admin-api.service.js // Provides all methods which communicate with admin api directly
            `-- fixture.service.js // Provides all methods for general fixture handling
  |-- saleschannel // this one stores the sales channel API services
  `-- api.service.js // axios interface
```

If you want to use all known services, you can access them using custom commands. These commands can be found in
`cypress/support/commands/api-commands.js` for general operation and `cypress/support/commands/fixture-commands.js`
specifically for fixture handling.

### Default fixture command

The fixed fixtures mentioned in the paragraph "Cypress' fixtures" can be sent to Shopware's REST API directly: In most
cases Shopware does not need any additional data, like IDs or other data already stored in Shopware.
That means the request can be sent and the desired entity can be created immediately: You just need to use the
`createDefaultFixture(endpoint, options = [])` command, as seen below:

```javascript
    beforeEach(() => {
        cy.createDefaultFixture('tax');
    });
```

In this example, a tax rate will be created with the data provided based on the `json` file
located in the `fixtures` folder. Let's look at the command in detail:

```javascript
Cypress.Commands.add('createDefaultFixture', (endpoint, data = {}, jsonPath) => {
    const fixture = new Fixture();
    let finalRawData = {};

    if (!jsonPath) {
        jsonPath = endpoint;
    }

    // Get test data from cy.fixture first
    return cy.fixture(jsonPath).then((json) => {

        // Merge fixed test data with possible custom one
        finalRawData = Cypress._.merge(json, data);

        // Create the fixture using method from fixture service
        return fixture.create(endpoint, finalRawData);
    });
});
```

### Commands of customised services

You will notice soon that some entities need data which is already available in Shopware. That means you have to
find out specific IDs or employ a completely different handling. In this case, an own service has to be created,
located in `e2e/cypress/support/service`. Some examples for these services are:

* Customer
* Sales channel
* Languages
* Products

In most cases, the usage of these services is similar to the basic one, if they are already implemented. There are
commands for each of those services provided by our E2E testsuite package. You don't need to define the API endpoint 
when using those commands. As these services are extending the FixturesService, all methods of it can be used
in all other services as well.

### Writing your own customised service

Let's look at the custom service `shipping.fixture.js`. This service is a rather simple example for a service
that needed some customizing for creating a shipping method correctly. With that being said, let's start.

Your `ShippingFixtureService` has to extend `AdminFixtureService`, so that we can fully access all data and
functions in it. Afterwards, you create a function called `setShippingFixture(userData)` with the parameter `userData`
for the data you want to use to create your shipping method. This way, your class should look like this:

```javascript
const AdminFixtureService = require('../fixture.service.js');

class ShippingFixtureService extends AdminFixtureService {
    setShippingFixture(userData) {
        // Here we're going to create our shipping fixture in Shopware
    }
}

module.exports = ShippingFixtureService;

global.ShippingFixtureService = new ShippingFixtureService();
```

All custom services hold a distinct method for creating fixtures: First, it's important to collect the necessary data
via REST API. This is done by filtered POST requests used in promises.
In case of your our `ShippingFixtureService`, you need the ID of the rule you want to use for the availability
and the ID of the delivery time.

```javascript
 const findRuleId = () => this.search('rule', {
        type: 'equals',
        value: 'Cart >= 0 (Payment)'
    });
 const findDeliveryTimeId = () => this.search('delivery-time', {
    type: 'equals',
    value: '3-4 weeks'
});
```

The responses of these calls are used to provide the missing IDs for your final POST request. At first, we will merge
the missing data with the existing data, then create our shipping method:

```javascript
return Promise.all([
    findRuleId(),
    findDeliveryTimeId()
]).then(([rule, deliveryTime]) => {
    return this.mergeFixtureWithData(userData, {
        availabilityRuleId: rule.id,
        deliveryTimeId: deliveryTime.id
    });
}).then((finalShippingData) => {
    return this.apiClient.post('/v1/shipping-method?_response=true', finalShippingData);
});
```

That's it! And there you go: You have successfully created a customised service that sets up
a shipping method in Shopware. Actually, we use this service in our platform test to create our shipping method as well.
You can find the full service
[here](https://github.com/shopware/e2e-testsuite-platform/blob/master/cypress/support/service/administration/fixture/shipping.fixture.js).
So please look at this example to see the whole class.

Below you will find some best practices and tricks we explored to help you with your testing tasks:

* A source of information can be found in FieldCollection of the several EntityDefinition files.
All fields belonging to an entity are defined there. For example, if you're searching for customer related data,
please search for CustomerDefinition in Shopware platform.
* Furthermore, you can always look at our Swagger UI every Shopware installation is coming with to
find out the correct data structure besides looking up the EntityDefinitions. The UI can be found via
`http://<your-installation>/api/v3/_info/swagger.html`.
* If you want to extract mandatory data that is not covered by the error message received with the API's response,
it's useful to reproduce your workflow manually: E.g. if you need to find out what data is mandatory for creating
a customer, try to save an empty one in the administration. Keep an eye on the developer tools of your browser while
doing so, especially on the preview and response section of your request. As you get your response,
you can see what data is still missing.
* If you need to set non-mandatory data, reproducing the above mentioned workflow is recommended as well:
Even if the error response does not contain a readable error, you can still inspect it: All the relevant information
is stored in 'data'. IDs can be found there directly, other relevant data is stored in "attributes".
* Cypress' test runner can help you a lot with inspecting API requests. Just click on the request in the test runner's
log to get a full print of it in your console.
