export default class GeneralPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {
            // General components
            alertSuccess: '.alert-success',
            cardTitle: '.card-title',

            // Create/detail components
            primaryButton: '.btn-primary',
            lightButton: '.btn-light'
        };
    }
}
