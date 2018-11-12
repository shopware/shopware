module.exports = {
    '@tags': ['storefront', 'homepage'],

    'logo found': (browser) => {
        browser
            .waitForElementVisible('body')
            .waitForElementVisible('.logo--shop')
            .assert.visible('.logo--shop .logo--link')
    },

    'search bar found': (browser) => {
        browser
            .assert.visible('.entry--search input[name="search"]')
            .end();
    }
};