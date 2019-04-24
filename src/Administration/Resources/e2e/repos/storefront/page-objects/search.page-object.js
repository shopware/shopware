const GeneralPageObject = require('./general.page-object');

class SearchPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);
        this.browser = browser;

        this.elements = {
            ...this.elements,
            ...{
                searchInput: '.form-inline input[type="text"]'
            }
        };
    }
}

module.exports = (browser) => {
    return new SearchPageObject(browser);
};
