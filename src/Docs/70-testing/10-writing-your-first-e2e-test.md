In this guide, you will learn how to write your first e2e test. With e2e testing we are able to put our UI under constant stress and ensure that Shopware's main functionalities 
are always working correctly. As we are using Nightwatch.js as testing framework, please refer to <http://nightwatchjs.org/> for further documentation.

## Setting up your environment

Let's assume you already got your dev environment running. If not, please follow the steps mentioned in the [guide](../1-getting-started/20-getting-started.md).

In e2e tests, you do not want to use any demo data which was not created by yourself explicitly. This clean and empty Shopware installation by executing `./psh.phar init` beforehand. 
The command `./psh.phar e2e:init` includes this init-step, so you don't have to bother with it yourself for the start: So as soon as you want to start writing e2e tests, run the command `e2e:init`. 
This way, `init` will set up your Shopware installation, the administration will be build and all your dependencies will be installed or updated. Third can be accomplished separately by running `administration:e2e-install`.

## Spec - The single test file

Now, it's time to start writing your first test! Your starting point is creating a folder in `/e2e/repos/administration/specs/` and if needed, creating a new directory for the area you want to test. 
For example, let's assume you want to test if the dashboard can be opened. So we create a directory with the name `dashboard`.

After that, you create the file which will contain your test, e.g. `overview-open.spec.js`. For details about the structure of spec files, you can read the corresponding section "Spec files: Test suite structure"  of the in-depth [guide](../70-testing/20-e2e-nightwatch-tests-in-detail.md).

Have you noticed the tags? These tags ensure the ability to run a single test as well as specific groups of test suites.: `'@tags': ['dashboard','overview-opem', 'open'],` 
You should define these tags from abstract to detail, including a tag which makes the execution of the single test possible. An example of this can be found in `create-manufacturer.spec.js`:

```javascript
'@tags': ['product', 'manufacturer-create', 'manufacturer', 'create', 'upload'],
```

These tags can be added to the `e2e:run` command by using the parameter `--NIGHTWATCH_PARAMS="--tag dashboard"`. In this example, only test tagged "dashboard" will be run.

After having set these initial definitions, you can start writing the actual test steps:

```javascript
'view dashboard': (browser) => {
       browser
           .waitForElementVisible('.sw-dashboard-index__content');
},
```
*One single test*

The browser will execute each step defined in this tests. The first part of this test syntax is naming the test: The string in the quotes will be printed out as title of this test. It looks like this:

```
Running:  view dashboard
```

Now you are ready to write the commands that define the workflow to be run in your browser. You can use both commands and assertions: In both cases Nightwatch.js already offers various possibilities. 
However, feel free to write your own commands or assertions if needed. Please refer to the Nightwatch documentation for more details on this topic. Shopware already provides some custom commands which are documented here. 

## Using test hooks

By now you should have noticed that there are steps necessary before and after running your tests. 
These steps called "hooks" ensure the test's correct execution by providing a predefined set of existing data or steps that are applied prior to or after every single test suite.

```javascript
after: (browser) => {
    browser.end();
}
```

This after-Hook is used to end the browser after running our tests. It will be executed after our tests are finished. On top of that, the login to Shopware and other steps are executed in the global `beforeEach` hook, 
so you don't have to bother yourself with these necessary but redundant steps when writing new tests.

You will find more detailed information about those lifecycle hooks in the corresponding paragraph "Test hooks in detail" of the in-depth [guide](../70-testing/20-e2e-nightwatch-tests-in-detail.md).

## Finally: Running our first test

In theory, you're done with writing your first test. It should look like this:

```javascript
module.exports = {
    '@tags': ['dashboard','overview-open', 'open'],
    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content');
    },
    after: (browser) => {
        browser.end();
    }
};

```
*Your test suite as a whole*

When you're finished writing your test or you want to see if it's already working, you may just simply want to run it. To start your test, please enter the command `e2e:run`. 
You can use it for local development as well as in your docker container. By using the `e2e:run` command without any restricting tags all tests available in your repository will be run.

If you want to run just a few tests, consider to add the tags as mentioned before. In your example, you can add the tags to the run-command as seen below:

```javascript
./psh.phar e2e:run --NIGHTWATCH_PARAMS="--tag dashboard"
```

You will get the following console output while running the tests. Congratulations - You have successfully written your first e2e test suite! :)
