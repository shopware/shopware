class SearchPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {
            searchInput: '.entry--search input[name="search"]'
        };
    }
}

module.exports = (browser) => {
    return new SearchPageObject(browser);
};
