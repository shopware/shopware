import elements from '../sw-general.page-object';

export default class ProductPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                productSaveAction: '.sw-product-detail__save-action',
                productListName: `${elements.dataGridColumn}--name`,
            },
        };
    }

    createTag(value) {
        cy.get('.sw-product-category-form__tag-field input')
            .type(value);
        cy.get('.sw-select-result-list-popover-wrapper').contains(`Add "${value}"`);
        cy.get('.sw-product-category-form__tag-field input')
            .type('{enter}');
        cy.get('.sw-select-result-list-popover-wrapper').contains(value);
        cy.get('.sw-product-category-form__tag-field input').type('{esc}');
    }

    changeTranslation(language, position) {
        cy.get('.sw-language-switch .sw-loader').should('not.exist');
        cy.get('.sw-language-switch').click();
        cy.get(`.sw-select-result-list__item-list .sw-select-option--${position}`)
            .contains(language)
            .click();
        cy.get('.sw-language-switch .sw-loader').should('not.exist');
    }


    generateVariants(propertyName, optionPosition, totalCount, prices = undefined) {
        const optionsIndicator = '.sw-property-search__tree-selection__column-items-selected.sw-grid-column--right span';
        const optionString = totalCount === 1 ? 'value' : 'values';

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post',
        }).as('productCall');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post',
        }).as('searchCall');

        cy.contains(propertyName).click();

        for (const entry in Object.values(optionPosition)) { // eslint-disable-line
            if (optionPosition.hasOwnProperty(entry)) {
                cy.get(
                    `.sw-property-search__tree-selection__option_grid .sw-grid__row--${entry} .sw-field__checkbox input`,
                ).click();
            }
        }

        if (prices !== undefined) {
            cy.get('.sw-tabs-item.sw-variant-modal__surcharge-configuration').click();
            cy.get('.sw-product-variants-configurator-prices').should('be.visible');
            cy.get('.sw-product-variants-configurator-prices__groups').contains(propertyName).click();

            for (const entry of prices) {
                const [row, currency, field, value] = entry;
                cy.get(`.sw-data-grid__row--${row} #sw-field--price-${field}`)
                    .eq(Number(currency)).scrollIntoView().clear().type(value).blur();
            }
        }

        cy.get(`.sw-grid ${optionsIndicator}`)
            .contains(new RegExp(`${optionPosition.length} (values? |)(selected|geselecteerde waarden|geselecteerde waarde)`));
        cy.get('.sw-product-variant-generation__generate-action').click();
        cy.get('.sw-product-modal-variant-generation__notification-modal').should('be.visible');

        if (totalCount !== 1) {
            cy.get('.sw-product-modal-variant-generation__notification-modal .sw-modal__body')
                .contains(new RegExp(`${totalCount} (variants will be added|varianten worden toegevoegd)`));
        }

        cy.get('.sw-product-modal-variant-generation__notification-modal .sw-button--primary').click()
            .then(() => {
                cy.get('.generate-variant-progress-bar__description').contains(new RegExp(`0 (of|van) ${totalCount} (variations generated|Varianten gegenereerd)`));
                cy.get('.sw-product-modal-variant-generation__notification-modal').should('not.exist');
            });

        cy.wait('@productCall').its('response.statusCode').should('equal', 200);

        cy.wait('@searchCall').its('response.statusCode').should('equal', 200);
        cy.get('.sw-product-modal-variant-generation').should('not.exist');
    }
}
