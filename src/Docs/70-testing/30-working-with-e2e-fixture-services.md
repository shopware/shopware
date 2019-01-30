To keep all tests isolated, it's utmost important that a test suite creates mandatory test data by itself and for their own scope respectively. 
In Shopware platform we use SHOPWARE's REST API to request and in response, to create the data we need. As a result, our tests are able to focus on one single workflow without having to test the routines which should only provide the data we need.

## Basics

### API implementation

Analogue to the administration itself, the api access of the e2e test is based on axios, a promise based HTTP client for the browser and node.js. You can find its documentation here: <https://www.npmjs.com/package/axios>

Just like the administration, we use services to access Shopware's REST API. Therefore we use the service `ApiService` for providing the basic methods for accessing the api. 
Located in `/e2e/common/service/api.service.js`, ApiService is shared between all repositories and serves as a basis for all your next steps of creating fixtures. 

That implies that the axios implementation of all important api methods can be found there. This service serves as an interface: Next to the basic functions like `get`,`post` etc, the request method is specified here as well as some Shopware-related methods which have to be available in all repositories.
+
For this reason, an implementation of the `ApiService` is located in every repository of the e2e tests. For example, the implementation in the administration repo can be found in `e2e/repos/administration/service/admin-api.service.js`. 
This service called `AdminApiService` implements the methods of `ApiService` in a fully tailored version for the administration: Aside of the request handling, it provides the authentication method.

### @fixtures: Fixed test data

To define the request you send to Shopware and to set first test data, you store your information in the folder `/e2e/common/@fixtures`. 
The files in this folder use the `json` format to provide the structure and data Shopware needs to create the fixtures. To give an example, please look at the file `customer.json`:

```json
{
  "customerNumber": "C-1232123",
  "salutation": "Mr",
  "firstName": "Pep",
  "lastName": "Eroni",
  "email": "test@example.com",
  "guest": true,
  "addresses": [
    {}
  ]
}
```
*The json file to define a customer*

As the name implies, this file is used in order to define and create a customer. Furthermore, it already provides data so that the customer can be created by Shopware. 
As a result, you can use those files to provide fixed test data which can be used directly to create the desired entity without any further searching or processing. 

Important! As these fixtures define the structure of the requests, they should generally not be changed, and even more important, not be deleted unless you come up with a well proposed reasoning for doing so anyway.

### Default Fixture service

As said before, these fixed fixtures can be sent to Shopware's REST API directly: Shopware does not need any additional data, like IDs or other data already stored in Shopware. 
That means the request can be sent and the desired entity can be created immediately. In this case, it's sufficient to use the basic `FixtureService` found in `e2e/repos/administration/service/fixture.service.js`: This service deploys a 'create()' function, creating fixture according to the specifications set down in the corresponding `json`-file and optional user data.
 
In short, you can use this general service in case you can freely define your fixture without further search for given entities in Shopware by using the Shopware REST API.

```javascript
before: (browser, done) => {
    global.FixtureService.create('api-endpoint').then(() => {
        done();
    });
},    
```
*before-hook with use of the basic FixtureService*

In general, the `FixtureService` serves as a basic for any custom services, providing all functions for successfully creating a fixture. Besides the function to create a basic fixture, it provides the following further methods:
* Loading the `fixture.json`-files and making their content accessible
* Merging all data in order to set them in a format the API understand
* Create UUIDs
* Ensures automatic loading of all fixture services

### globals.js and before-hooks 

In order to be accessible globally, the integration of the file of the `FixtureServise` is located in `globals.js`. As described in paragraph "before-hooks with fixtures" of the in depth [guide concearning nightwatch tests](../30-api/30-special-resources.md), the application of these services is located in the before-hooks of the test suites. An example of a service's usage below:

```javascript
before: (browser, done) => {
    global.FixtureService.create('api-endpoint').then(() => {
        done();
    });
}   
```
*service usage in test suite*

### Using different data in test suite

I
If you want to use data not already stated in the `fixture.json` files, create a separate fixture object containing the data you want to change within your test suite:

```javascript
const fixture = {
    name: '2nd Epic Sales Channel',
    accessKey: global.FixtureService.createUuid()
};
```

Afterwards, you just need to pass your `fixture` object to the service. It will be merged with data from `fixture.json` by the service itself.

```javascript
    before: (browser, done) => {
        global.SalesChannelFixtureService.setSalesChannelFixture(fixture).then(() => {
        ...
    },
```
*Using custom test data*

## Customised services

You will soon notice that some entities need data which is already available in Shopware. That means you have to find out specific IDs or employ a completely different handling.
In this case, an own service has to be created, located in `/e2e/common/service/fixture`. Some examples for these services are:
* Customer
* Sales channel
* Languages
* Products
* Integration

In most cases, the usage of these services is similar to the basic one. You don't need to define the API endpoint though. As these services are extending the `FixturesService`, all methods of it can be used in all other services as well.

```javascript
const fixture = {
    name: '2nd Epic Sales Channel',
    accessKey: global.FixtureService.createUuid()
};

module.exports = {
    '@tags': ['sales-channel-edit', 'sales-channel', 'edit'],
    before: (browser, done) => {
        global.SalesChannelFixtureService.setSalesChannelFixture(fixture).then(() => {
            done();
        });
    },
```
*Usage of sales channel services*

As these services are highly customised, it is difficult to find one example to explain them in detail. However, you can follow an example in the paragraph up next or read our tipps. 

## Writing your own customised service

Let's look at the custom service `integration.fixture.js`. 
This service is a rather simple example for a service that needed some customizing for creating an integration correctly and displaying its result as log entry.

With that being said, let's start. Your `IntegrationFixture` service has to extend `FixtureService`, so that we can fully access all data and functions in it. 

```javascript
const FixtureService = require('./../fixture.service.js').default;

export default class IntegrationFixtureService extends FixtureService {
    constructor() {
        super();
    }

}

global.IntegrationFixtureService = new IntegrationFixtureService();
```

At first, you want to make sure that the `integration.json` is accessible in your service as base fixture. This way, you can access your data from everywhere in your tests.

```javascript
const FixtureService = require('./../fixture.service.js').default;

export default class IntegrationFixtureService extends FixtureService {
    constructor() {
        super();
        this.integrationFixture = this.loadJson('integration.json');
    }

    setIntegrationBaseFixture(json) {
        this.integrationFixture = json;
    }
}

global.IntegrationFixtureService = new IntegrationFixtureService();
```

After that, you can take care of the most important function of your custom service: The creation of your integration fixture.

```javascript
const FixtureService = require('./../fixture.service.js').default;

export default class IntegrationFixtureService extends FixtureService {
    constructor() {
        super();
        this.integrationFixture = this.loadJson('integration.json');
    }

    setIntegrationBaseFixture(json) {
        this.integrationFixture = json;
    }

    setIntegrationFixtures(userData) {
        // Here we're going to create our integration fixture in Shopware
    }
}

global.IntegrationFixtureService = new IntegrationFixtureService();
```

All custom services hold a distinct method for creating fixtures: First, it's important to collect the necessary data via REST API. This is done by filtered POST requests, as described in the API guide concerning [special resources](../30-api/30-special-resources.md). As soon 
In case of your `IntegrationFixture`, you will need the ID of the integration you just created in order to display it. 

```javascript
setIntegrationFixtures(userData) {
    global.logger.lineBreak();
    global.logger.title('Set integration fixtures...');

    const finalRawData = this.mergeFixtureWithData(this.integrationFixture, userData);

    // create integration fixture via POST request
    return this.apiClient.post(`/v1/integration?response=true`, finalRawData)
        .then(() => {
            // search for newly created integration fixture by label
            return this.apiClient.post(`/v1/search/integration?response=true`, {
                filter: [{
                    field: "label",
                    type: "equals",
                    value: finalRawData.name,
                }]
            });
        }).catch((err) => {
            global.logger.error(err);
            global.logger.lineBreak();
        }).then((data) => {
            // Printing out the id of the new integration as success message
            global.logger.success(data.id);
            global.logger.lineBreak();
        });
}
```
*Creation of the integration fixture*

After some log entries signalising the beginning of creation, it will merge the data from `integration.json` with the data passed from the test suite. Afterwards, you can already start creating your integration by sending the request to Shopware.

However, to print the result of the creation as log entry, you need the ID of your new integration. Deviating from other entities, the name of an integration is stored in the field "label" instead of the field "name", so you need to adjust your request in oder to get its ID:

```javascript
return this.apiClient.post(`/v1/search/integration?response=true`, {
    filter: [{
        field: "label",
        type: "equals",
        value: finalRawData.name,
    }]
});
```
*Customised search request*

As you receive the ID of your integration, you can use it in order to print it out as log entry. And there you go: You have successfully created a customised service that sets up 
an integration and assesses possible outcomes properly. Of course, depending on which fixture you need, you might be up to some trial and error problem solving, 
in order to find the exact data needed to reach your test goals. Below you will find some best practices and tricks we found to help you with your testing tasks:

* If you want to extract mandatory data that is not covered by the error message received with the API's response, it's useful to reproduce your workflow manually:
E.g. if you need to find out what data is mandatory for creating a customer, try to save an empty one in the administration. Keep an eye on the developer tools of your browser while 
doing so, especially on the 'preview' and 'response section of your request. As you get your response, you can see what data is still missing.
* If you need to set non-mandatory data, reproducing the above mentioned workflow is recommended as well: Even if the error response does not contain a readable error, 
you can still inspect it: All the relevant information is stored in 'data'. IDs can be found there directly, other relevant data is stored in "attributes".
* Another source of information can be found in FieldCollection of the several `EntityDefinition` files. All fields belonging to an entity are defined there. For example, 
if you're searching for customer related data,, please search for `CustomerDefinition` in Shopware platform.

