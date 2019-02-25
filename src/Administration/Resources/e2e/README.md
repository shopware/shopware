# E2E test suite

The E2E test suite is a mono repository for Nightwatch.js tests. The tests are split up into multiple repositories for
a better scalability and getting it independent from the administration.

## Features
* ES6 support using `babel-register`
* Split up code base in repositories
    * Custom commands, spec folders, globals & custom config per section
* Node.js path resolver will be extended
    * Relative paths are no longer necessary
* Support for `tag`, `skiptags` & `groups` using a PSH parameter
* ESLint support using a custom configuration

## Installation
The test suite is independent from the administration and has its own `package.json` to work with. Therefore the test
suite needs to be installed as well:

```bash
./psh.phar e2e:init
```

## Running tests

After the installation of the dependencies, tests can be executed using PSH:

```bash
./psh.phar e2e:run
```

### Additional parameters
It's possible to add additional Nightwatch.js parameters using the PSH parameter `--NIGHTWATCH_PARAMS`. 

```bash
./psh.phar e2e:run --NIGHTWATCH_PARAMS="--tag sales-channel"
```

### Run a different repo
Running a different repository is as simple as providing an additional PSH parameter.

```bash
./psh.phar e2e:run --NIGHTWATCH_ENV="installer"
```

## Structure
The test suite is separated into the different section (called repository) of Shopware. Each repository can have their
own configuration file and therefore their own globals, src directory, globals file and settings.

```bash
└── repos
    ├── administration
    │   ├── custom-commands
    │   ├── specs
    │   ├── globals.js
    │   └── nightwatch.conf.js
    └── installer
        ├── specs
        └── nightwatch.conf.js
```

Please keep in mind a `nightwatch.conf.js` file is mandatory when the repository needs custom commands, globals or launch_url etc. 

## Running ESLint
The test suite provides two additional NPM scripts which are used for ESLint:

```bash
npm run lint # Runs ESLint
npm run fix  # Runs ESLint and automatically fixes errors
```

## Additional path resolving
The test suite extends the Node.js path resolving which results in clean path when requiring modules. The folder `repos`
will be added so paths can be absolute starting from this folder.

In the following example we're loading a module from the administration page objects in a nested test spec:

```js
/** Before */
require('../../../page-objects/sw-integration.page-object.js');

/** After */
require('administration/page-objects/sw-integration.page-object.js');
```

## ES6 support
We're using `babel-register` to support ES6 features in the specs, page-objects and custom command files. Please keep in
mind that `babel-register` hooks into Node.js's `require` function to transpiles the files on-the-fly which results
in a slower startup and has a higher memory-consuming.

## Headless Mode
Nightwatch.js will be started in headless mode. If you want to watch what Nightwatch.js is doing, you can use the PSH
parameter `NIGHTWATCH_HEADLESS`:

```bash
./psh.phar e2e:run --NIGHTWATCH_HEADLESS="false"
```

## Further documentation
You can find a detailed documentation about the E2E test suite in the official [Shopware docs](https://docs.shopware.com/en/shopware-platform-en/testing).
