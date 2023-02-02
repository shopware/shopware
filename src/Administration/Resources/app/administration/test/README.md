# Jest tests for the administration

This little guide will guide you how to write unit tests for the administration in Shopware 6.

## When should I write unit tests
You should write a unit test for every functional change. It should guarantee that 
your written code works and that a third developer don't break the functionality with his code.

With a good test coverage we can have the confidence to deploy a stable software without extra
manual testing.

## General information
We are using [Jest](https://jestjs.io/) as our testing framework. It is a solid foundation and widely
used by many developers. Before you are reading this guide you have to make sure that you understand the
basics of unit tests and how Jest works.

You can find good source for best practices in this Github Repo: 
[https://github.com/goldbergyoni/javascript-testing-best-practices](https://github.com/goldbergyoni/javascript-testing-best-practices) 

## Folder structure
The test folder structure should match the source folder structure. You add a test for a file in the same
path as the source path. The name should also be the same with an additional `.spec` before the ending `.js`.
As an example a test for `src/core/service/login.service.js` should be created
in `test/core/service/login.service.spec.js`. 

## Test commands
Before you are using the commands make sure that you installed all dependencies for your adminstration.
If you didn't have done this already then you can use this PSH command:
`./psh.phar administration:install-dependencies`

#### Run all unit tests:  
This command executes all unit tests and show you a complete code coverage.  
`./psh.phar administration:unit`


#### Run only changed files:  
This command executes only unit tests on changed files. It automatically restarts if a file
get saved. This should be used during the development of unit tests.  
`./psh.phar administration:unit-watch`



## Setup for testing services and ES modules
Services and isolated EcmaScript modules are good testable because
you can import them directly without mocking or stubbing dependencies.

Lets have a look at an example:

```javascript
// sanitizer.helper.spec.js

import Sanitizer from 'src/core/helper/sanitizer.helper';

describe('core/helper/sanitizer.helper.js', () => {
    it('should sanitize the html', () => {
        expect(Sanitizer.sanitize('<A/hREf="j%0aavas%09cript%0a:%09con%0afirm%0d``">z'))
            .toBe('<a href="j%0aavas%09cript%0a:%09con%0afirm%0d``">z</a>');
    });
    
    it('should remove script functions from dom elements', () => {
        expect(Sanitizer.sanitize('<details open ontoggle=confirm()>'))
            .toBe('<details open=""></details>');
    });
    
    it('should remove script functions completely', () => {
        expect(Sanitizer.sanitize(`<script y="><">/*<script* */prompt()</script`))
            .toBe('');
    });

    it('should sanitize js in links', () => {
        expect(Sanitizer.sanitize('<a href=javas&#99;ript:alert(1)>click'))
            .toBe('<a>click</a>');
    });

    // ...more tests 
});
``` 

The service can be used isolated and therefore is easy to test.

## Setup for testing Vue components
We are using the [Vue Test Utils](https://vue-test-utils.vuejs.org/) for easier testing of Vue components. When you don't
have experience with testing Vue components it is useful to read some basic guides how to do this. The main part of
testing components is similar in Shopware 6.

But there are some important differences. We can't test components that easily like in other Vue projects because we
are supporting template inheritance and extendability for third party developers. This causes overhead which we need
to bear in mind.

We are using a global object as an interface for the whole administration. Every component gets registered to this 
object, e.g. `Shopware.Component.register()`. Therefore we have access to Component with the `Shopware.Component.build()`
method. This creates a native Vue component with a working template. Every override and extension from another
components are resolved in the built component.

### Practical example
Fot better understanding how to write component tests for Shopware 6 let's write a test. In our example
we are using the component `sw-multi-select`.

When you want to mount your component it needs to be imported first:
```javascript
// test/app/component/form/select/base/sw-multi-select.spec.js

import 'src/app/component/form/select/base/sw-multi-select';
```

You see that we import the `sw-multi-select` without saving the return value. This
blackbox import only executes code. But this is important because this registers
the component to the Shopware object:
```javascript
// src/app/component/form/select/base/sw-multi-select.js

Shopware.Component.register('sw-multi-select', {
    // The vue component
});
```

In the next step we can mount our Vue component which we get from the global Shopware object:
```javascript
// test/app/component/form/select/base/sw-multi-select.spec.js

import 'src/app/component/form/select/base/sw-multi-select';

shallowMount(Shopware.Component.build('sw-multi-select'));
```

The `build` method resolves the twig template and returns a vue component. Now you can test the component like any other
Vue component. Lets try to write our first test: 
```javascript
// test/app/component/form/select/base/sw-multi-select.spec.js

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-multi-select';

describe('components/sw-multi-select', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-multi-select'));
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });
});
```
We create a new `wrapper` before each test. This contains our component. In our first test we only
check if the wrapper is a Vue instance. 

Now lets start the watcher to see if the test works. You can do this with our PSH command `./psh.phar administration:unit-watch`.
You should see a result like this: `Test Suites: 1 passed, 1 total`. Now we have a working test. You
should also see several warnings like this:

- `[Vue warn]: Missing required prop: "options"`
- `[Vue warn]: Missing required prop: "value"`
- `[Vue warn]: Unknown custom element: <sw-select-base> - did you register the component correctly? ...`

The first two warnings are solved easily by providing the required props to our shallowMount:
```javascript
wrapper = shallowMount(Shopware.Component.build('sw-multi-select'), {
    props: {
        options: [],
        value: ''
    }
});
```

Now you should only see the last warning with an unknown custom element. The reason for this is that
most components are containing other components. In our case the `sw-multi-select` needs the 
`sw-select-base` component. Now we have several solutions to solve this. The two most common ways
are stubbing or using the component.

```javascript
import 'src/app/component/form/select/base/sw-select-base'; // Option 2: You need to import the component first before using it

wrapper = shallowMount(Shopware.Component.build('sw-multi-select'), {
    props: {
        options: [],
        value: ''
    },
    stubs: {
        'sw-select-base': true, // Option 1: Auto Stub the component
        'sw-select-base': Shopware.Component.build('sw-select-base'), // Option 2: Create the component
    }
});
```

You need to choose which way is needed. Many tests do not need the real component. But in our case we
need the real implementation. You will see that if we import another component that they can create
also warnings. Lets solve all warnings and then we should have a code like this:

```javascript
// test/app/component/form/select/base/sw-multi-select.spec.js

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-button';

describe('components/sw-multi-select', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-multi-select'), {
            props: {
                options: [],
                value: ''
            },
            stubs: {
                'sw-select-base': Shopware.Component.build('sw-select-base'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-icon': {
                    template: '<div></div>'
                },
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
                'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
                'sw-popover': Shopware.Component.build('sw-popover'),
                'sw-select-result': Shopware.Component.build('sw-select-result'),
                'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
                'sw-label': Shopware.Component.build('sw-label'),
                'sw-button': Shopware.Component.build('sw-button')
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });
});
```

The more components you are depending the more you have to create a complex setup for the test. Your 
component get also depends on other dependencies like services or injections. Most dependencies are provided by the default setup. But in some cases you need to mock them also. Here you can find the documentation from Vue-Test-Utils how to do this: https://vue-test-utils.vuejs.org/api/options.html#mocks

## Write tests for components

After setting up your component test you need to write your tests. A good way to write them is to test input
and output. The most common tests are:

- set Vue Props and check if component looks correctly
- interact with the DOM and check if the desired behaviour is happening

But it depends on what you are trying to achieve with your component. Here are some examples:
```javascript
it('should render uppercase transformation when checkbox is checked', () => {
    wrapper.setProps({ value: 'This is an example' });
    
    const checkboxShowUppercase = wrapper.find('.checkbox-show-uppercase');
    expect(checkboxShowUppercase.element.value).toBeTruthy();

    const labelText = wrapper.find('.field-label').text();
    expect(labelText).toContain('THIS IS AN EXAMPLE');
})

it('should disable uppercase transformation when checkbox is unchecked', () => {
    wrapper.setProps({ value: 'This is an example' });

    const checkboxShowUppercase = wrapper.find('.checkbox-show-uppercase');
    
    expect(checkboxShowUppercase.element.value).toBeTruthy(); 
    checkboxShowUppercase.trigger('click');
    expect(checkboxShowUppercase.element.value).toBeFalsy();

    const labelText = wrapper.find('.field-label').text();
    expect(labelText).toContain('This is an example');
})


it('should emit the new uppercase value', () => {
    wrapper.setProps({ value: 'This is an example' });
    
    expect(wrapper.emitted().length).toBe(0);

    const updateTextValueButton = wrapper.find('.button-updateTextValue');
    updateTextValueButton.trigger('click');

    expect(wrapper.emitted().length).toBe(1);
    expect(wrapper.emitted().input).arrayContaining(['THIS IS AN EXAMPLE']);
})

it('should render a new joke from api', async () => {
    jokeService.getJoke = jest.fn(() => {
        return Promise.resolve({ joke: 'What did one wall say to the other? Meet you at the corner!' });
    });

    const actualJoke = wrapper.find('.joke');
    expect(actualJoke.text()).toEqual('');

    const fetchJoke = wrapper.find('.button-fetchJoke');
    fetchJoke.trigger('click');

    await wrapper.vm.$nextTick();

    expect(actualJoke.text()).toEqual('What did one wall say to the other? Meet you at the corner!');
    jokeService.getJoke.mockReset();
})
```

## Using preconfigured mocks
To improve the test writing experience we included many mocks, helper methods and more by default. This will help to reduce the overhead of setting up a single test with all mocks. Everything can be overwritten in the `mount` or `shallowMount` method if you need to have custom implementation.

The actual implementation of the environment preparation is happening in this file: `test/_setup/prepare_environment.js` 

### ACL
You can set the active ACL roles simply by adding values to the global variable `global.activeAclRoles`. By default, the test suite has no ACL rights. You can change the privileges for each test if you want.

Example:
```js
it('should render with ACL rights', async () => {
    // set ACL privileges
    global.activeAclRoles = ['product.editor'];

    const wrapper = await createWrapper();
    expect(wrapper.vm).toBeTruthy();
});
```

### Feature flags
When you want to enable feature flags you can add the flag to the global variable `global.activeFeatureFlags`. You can change the feature flags for each test if you want.

Example:
```js
it('should render with active feature flag', async () => {
    // set feature flag
    global.activeFeatureFlags = ['FEATURE_NEXT_12345'];

    const wrapper = await createWrapper();
    expect(wrapper.vm).toBeTruthy();
});
```

### Repository Factory
The data handling and the repository factory works by default. It will be generated by the entity-schema which will be written to a file before you start the test suite.

Every time the repository factory request something from a URL you get a notification in the console. This notification also includes a short guide how to implement the response. This information could look something like this:

```
You should to implement mock data for this route: "/search/product".

############### Example ###############

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/product',
    status: 200,
    response: {
        data: [
            {
                id: YourId,
                attributes: {
                    id: YourId
                }
            }
        ]
    }
});

############### Example End ###############

You can disable this warning with this code:

global.repositoryFactoryMock.showError = false;
```

The response value should contain your test data. It needs to match the response from the backend API. An easy way to get the correct response it to inspect the response from the real API when you open the administration.

If you don´t want to use this helper then you can easily overwrite it by setting a custom mock for the repositoryFactory in your mount method.

### Directives
All global directives are registered by default. You can overwrite them if you want.

### Filters
All global filters are registered by default. You can overwrite them if you want.

### Services
Some services are registered with a mock alternative. If you use another service then you need to mock it manually. The console will inform you with a warning that the service does not exist.

### Context
The global Shopware context is prepared automatically. You can overwrite them in the Shopware.Store if you need to.

### Global mocks
For most cases we created automatic mocks, so you don´t need to implement them manually. Some examples are `$tc`, `$device`, `$store` or `$router`.

If you want to override one mock then you can do it in the mount method:
```js
mount('dummy-component', {
    mocks: {
        $tc: (...args) => JSON.stringify([...args])
    }
})
```

The `$router` and `$route` mocks could potentially lead to errors, if you need to for example need to provide your own real router. This can be mitigated by removing those global mocks, before mounting the component, like this:

```js
// delete global $router and $routes mocks
delete config.mocks.$router;
delete config.mocks.$route;
```

## Allowing errors

By default jest tests fail if an error or a warning is logged to the console. This can be disabled for specific errors and warnings by adding them to a allow list for a given test, like this:

```js
// Turn off known errors
import { unknownOptionError } from 'src/../test/_helper_/allowedErrors';

global.allowedErrors = [ unknownOptionError ];
```

If an error or a warning doesn't already exist in the `_helper_/allowedErrors.js` file, then it can be added in this format: 

```js
export const unknownOptionError = {
    msg: /Given value "\w*|\d*" does not exists in given options/,
    method: 'warn'
};
```

Tests fail on errors and warnings to keep them as expressive as possible, so you should try to fix the errors and warnings instead of disabling them.
