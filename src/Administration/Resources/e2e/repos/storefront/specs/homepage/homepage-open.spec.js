module.exports = {
    '@tags': ['homepage', 'homepage-open'],
    'storefront visible': (browser) => {
        browser
            .assert.visible('body');
    },
    after: (browser) => {
        browser.end();
    }
};