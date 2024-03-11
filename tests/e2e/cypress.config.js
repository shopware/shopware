const { defineConfig } = require('cypress')

module.exports = defineConfig({
  viewportHeight: 1080,
  viewportWidth: 1920,
  watchForFileChanges: true,
  requestTimeout: 60000,
  responseTimeout: 80000,
  defaultCommandTimeout: 30000,
  nodeVersion: 'bundled',
  salesChannelName: 'Storefront',
  useDarkTheme: false,
  video: false,
  useShopwareTheme: true,
  theme: 'dark',
  screenshotsFolder: './../../var/log/e2e/screenshots',
  modifyObstructiveCode: false,
  env: {
    user: 'admin',
    pass: 'shopware',
    salesChannelName: 'Storefront',
    admin: '/admin',
    apiPath: 'api',
    locale: 'en-GB',
    shopwareRoot: '/app',
    localUsage: false,
    usePercy: false,
    minAuthTokenLifetime: 60,
    acceptLanguage: 'en-GB,en;q=0.5',
    dbUser: 'root',
    dbPassword: 'root',
    dbHost: 'mysql',
    dbName: 'shopware_e2e',
    expectedVersion: '6.6.',
    grepOmitFiltered: true,
    grepFilterSpecs: true,
  },
  retries: 0,
  reporter: 'cypress-multi-reporters',
  reporterOptions: {
    reporterEnabled: 'mochawesome, mocha-junit-reporter',
    mochawesomeReporterOptions: {
      reportDir: './../../var/log/e2e/results/mocha',
      quite: true,
      overwrite: false,
      html: false,
      json: true,
    },
    mochaJunitReporterReporterOptions: {
      mochaFile:
        './../../var/log/e2e/results/single-reports/results-[hash].junit.xml',
    },
  },
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      require('@cypress/grep/src/plugin')(config);

      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: process.env.APP_URL ?? 'http://localhost:8000',
  },
})
