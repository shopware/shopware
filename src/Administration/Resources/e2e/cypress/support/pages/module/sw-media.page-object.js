const GeneralPageObject = require('../sw-general.page-object');

export default class MediaPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                productSaveAction: '.sw-product-detail__save-action',
                productListName: `${this.elements.dataGridColumn}--name`
            }
        };
    }

    uploadImageUsingUrl(path) {
        cy.get('.sw-media-url-form').should('be.visible');
        cy.get('input[name=sw-field--url]').should('be.visible')
            .type(path);
        cy.get('.sw-media-url-form__submit-button').click();
        cy.awaitAndCheckNotification('A file has been saved successfully.');

        cy.get('.sw-media-preview__item').invoke('attr', 'src').should('contain', 'sw-login-background');

        return this;
    }
}
