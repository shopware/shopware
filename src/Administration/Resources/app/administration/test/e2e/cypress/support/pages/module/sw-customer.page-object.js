/* global cy */
import elements from '../sw-general.page-object';

export default class CustomerPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                customerForm: '.sw-customer-base-form',
                customerMailInput: 'input[name=sw-field--customer-email]',
                customerMetaData: '.sw-customer-card__metadata',
                customerSaveAction: '.smart-bar__actions button.sw-button--primary',
                columnName: `${elements.dataGridColumn}--firstName`
            }
        };
    }

    createBasicAddress(customer) {
        cy.get('.sw-customer-address-form__salutation-select')
            .typeSingleSelectAndCheck(customer.salutation, '.sw-customer-address-form__salutation-select');
        cy.get('input[name=sw-field--address-firstName]').type(customer.firstName);
        cy.get('input[name=sw-field--address-lastName]').type(customer.lastName);
        cy.get('input[name=sw-field--address-street]').type(customer.addresses[0].street);
        cy.get('input[name=sw-field--address-zipcode]').type(customer.addresses[0].zipcode);
        cy.get('input[name=sw-field--address-city]').type(customer.addresses[0].city);
        cy.get('.sw-customer-address-form__country-select')
            .typeSingleSelectAndCheck(customer.country, '.sw-customer-address-form__country-select');
    }
}
