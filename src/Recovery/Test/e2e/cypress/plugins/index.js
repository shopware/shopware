// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

// This function is called when a project is opened or re-opened (e.g. due to
// the project's config changing)

module.exports = (on, config) => {
    require('@shopware-ag/e2e-testsuite-platform/cypress/plugins');

    // TODO: Workaround to cypress issue #6540, remove as soon as it's fixed
    on('before:browser:launch', (browser, launchOptions) => {
        if (browser.name === 'chrome' && browser.isHeadless) {
            launchOptions.args.push('--disable-gpu');
            launchOptions.args.push('--enable-logging --v=1');
            return launchOptions;
        }
    });
}
