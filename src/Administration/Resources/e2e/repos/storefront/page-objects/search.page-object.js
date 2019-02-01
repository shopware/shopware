class SearchPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {
            searchInput: 'form-inline input[type="text"]'
        };
    }
}

module.exports = (browser) => {
    return new SearchPageObject(browser);
};
