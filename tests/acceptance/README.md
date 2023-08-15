# Shopware Acceptance Test Suite

## Introduction
The test suite is build with **Playwright**. For detailed information have a look into the [official documentation](https://playwright.dev/docs/).

## Setup

Install the project dependencies. 
```
npm install
```

Make sure to add the required environment variables to your `.env` file in the acceptance directory (not the shopware root).
```
APP_URL="<shop base url>"
SHOPWARE_ACCESS_KEY_ID="<your-api-client-id>"
SHOPWARE_SECRET_ACCESS_KEY="<your-api-secret>"
```

To generate the access key you can use the following symfony command:

`bin/console framework:integration:create AcceptanceTest --admin`

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

Debugging tests
```
npx playwright test --debug
```

Reduce worker count
```
npx playwright test --workers 4
```
