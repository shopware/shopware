[titleEn]: <>(Jest as testing framework (admin))

We made the shift from Karma (including Chai, Jasmine and Sinon) to Jest as our primary JavaScript testing framework. Jest provides us with a lot of functionality:

* Snapshot testing using the offical @vue/test-utils tool
* Mocks for ES6 classes, Timers including automatic mocking & clearing
* Spies are part of Jest, we don't need a separate framework anymore
* Running tests in parallel
* Debugging Support using Chrome Inspect
* Code Coverage report using Istanbul with a Clover Report + inline in the terminal


Documentation links:

* Matchers / expect: https://jestjs.io/docs/en/expect
* Vue Test Utils: https://vue-test-utils.vuejs.org/guides/

The existing tests have been converted to Jest' Matcher API using https://github.com/skovhus/jest-codemods

The test specs can be found in `Administration/Resources/app/administration/test`

###  Running tests
```bash
./psh.phar administration:unit
./psh.phar administration:unit-watch # Watch mode
```

### Snapshot Testing
![snapshot testing](https://jestjs.io/img/content/failedSnapshotTest.png)

Snapshot tests are a very useful tool whenever you want to make sure your UI does not change unexpectedly. It's supported using @vue/test-utils:

```javascript
import { shallowMount } from '@vue/test-utils';
import swAlert from 'src/app/component/base/sw-alert';

it('should render correctly', () => {
    const title = 'Alert title';
    const message = '<p>Alert message</p>';

    const wrapper = shallowMount(swAlert, {
        stubs: ['sw-icon'],
        props: {
            title
        },
        slots: {
            default: message
        }
    });
    expect(wrapper.element).toMatchSnapshot();
});
```

Snapshots are specialized files from Jest which are representing the actual DOM structure. If a refactoring changes the DOM structure unintentionally, the test will fail. The developer can either update the snapshot to reflect the new DOM structure when the structure change was intended or fix the structure until the test passes again.

### Shallow Mounting
Please consider prefering shallowMount instead of mount. Shallow mounting a component lets you stub additional components, fill slots, set props etc. Here's the documentation: https://vue-test-utils.vuejs.org/api/#shallowmount

### Vue Router Support
If your compomnent is using router-link or router-view, you can simply stub them:

```javascript
import { shallowMount } from '@vue/test-utils'

shallowMount(Component, {
    stubs: ['router-link', 'router-view']
});
```

#### INSTALLING VUE ROUTER FOR A TEST

```javascript
import { shallowMount, createLocalVue } from '@vue/test-utils'
import VueRouter from 'vue-router'

const localVue = createLocalVue()
localVue.use(VueRouter)

shallowMount(Component, {
    localVue
});
```

#### MOCKING $ROUTE
```javascript
import { shallowMount } from '@vue/test-utils'

const $route = {
    path: '/some/path'
};

const wrapper = shallowMount(Component, {
    mocks: {
      $route
    }
});

console.log(wrapper.vm.$route.path);
```

#### Triggering events

```javascript
const wrapper = shallowMount(Component);

wrapper.trigger('click');

// With options
wrapper.trigger('click', { button: 0 })
```
