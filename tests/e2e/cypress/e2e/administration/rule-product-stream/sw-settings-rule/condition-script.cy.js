// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

const uuid = require('uuid/v4');

function createTestRoleViaApi({ roleID, roleName }) {
    return cy.getCookie('bearerAuth').then((cookie) => {
        let headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${JSON.parse(cookie.value).access_token}`,
            'Content-Type': 'application/json',
        };

        return cy.request({
            url: `/${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
            headers: headers,
            body: {
                grant_type: 'password',
                client_id: 'administration',
                scope: 'user-verified',
                username: 'admin',
                password: 'shopware',
            },
        }).then(response => {
            // overwrite headers with new scope
            headers = {
                Accept: 'application/vnd.api+json',
                Authorization: `Bearer ${response.body.access_token}`,
                'Content-Type': 'application/json',
            };

            return cy.request({
                url: `/${Cypress.env('apiPath')}/acl-role`,
                method: 'POST',
                headers: headers,
                body: {
                    id: roleID,
                    name: roleName,
                    privileges: [],
                },
            });
        });
    });
}

describe('Rule builder: Test app script conditions', () => {
    beforeEach(() => {
        const roleID = uuid().replace(/-/g, '');

        createTestRoleViaApi({
            roleID: roleID,
            roleName: 'e2e-test-role',
        }).then(() => {
            return cy.createDefaultFixture('app', {
                aclRoleId: roleID,
            });
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @rule: test script conditions are selectable and rendered', { tags: ['pa-services-settings', 'quarantined'] }, () => {
        cy.get('a[href="#/sw/settings/rule/create"]').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        const conditions = {
            'Custom single select': 'sw-single-select',
            'Custom multi select': 'sw-multi-select',
            'Custom entity single select': 'sw-entity-single-select',
            'Custom entity multi select': 'sw-entity-multi-select',
            'Custom text': 'sw-field--text',
            'Custom int': 'sw-field--number',
            'Custom float': 'sw-field--number',
            'Custom bool': 'sw-field--checkbox',
            'Custom datetime': 'sw-field--datepicker',
            'Custom color': 'sw-colorpicker',
            'Custom media': 'sw-media-field',
            'Custom price': 'sw-field--number',
            'Custom textarea': 'sw-field--text',
            'Custom without fields': null,
        };

        Object.entries(conditions).forEach(([conditionName, fieldClass]) => {
            cy.get('.sw-condition-type-select__select')
                .typeSingleSelectAndCheck(
                    conditionName,
                    '.sw-condition-type-select__select',
                );

            if (fieldClass === null) {
                cy.get('.sw-condition-script').should('be.empty');

                return;
            }

            cy.get(`.sw-condition-script .${fieldClass}`).should('exist');
        });

        // switch to regular condition and back
        cy.get('.sw-condition-type-select__select')
            .typeSingleSelectAndCheck(
                'Customer group',
                '.sw-condition-type-select__select',
            );
        cy.get('.sw-condition .sw-condition-operator-select__select').should('exist');

        cy.get('.sw-condition-type-select__select')
            .typeSingleSelectAndCheck(
                'Custom text',
                '.sw-condition-type-select__select',
            );
        cy.get('.sw-condition .sw-condition-operator-select__select').should('not.exist');
        cy.get('.sw-condition-script .sw-field--text').should('exist');
    });

    it('@base @rule: test script conditions persist', { tags: ['pa-services-settings', 'quarantined'] }, () => {
        const page = new RulePageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'POST',
        }).as('loadData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule`,
            method: 'POST',
        }).as('saveData');

        cy.get('a[href="#/sw/settings/rule/create"]').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // fill basic data
        cy.get('input[name=sw-field--rule-name]').clearTypeAndCheck('Rule 1st');
        cy.get('input[name=sw-field--rule-priority]').clearTypeAndCheck('1');

        // add 3 more conditions
        cy.get('.sw-button.sw-condition-and-container__actions--and').click();
        cy.get('.sw-button.sw-condition-and-container__actions--and').click();
        cy.get('.sw-button.sw-condition-and-container__actions--and').click();

        const conditions = ['Custom text', 'Custom multi select', 'Custom without fields', 'Custom float'];

        conditions.forEach((name, index) => {
            cy.get('.sw-condition').eq(index).then((conditionElement) => {
                cy.get('.sw-condition-type-select', { withinSubject: conditionElement })
                    .then((conditionTypeSelect) => {
                        cy.wrap(conditionTypeSelect).click();
                        cy.get('.sw-select-result-list-popover-wrapper').contains(name)
                            .click();
                    });
            });
        });

        // save rule
        cy.get('.sw-settings-rule-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-skeleton').should('exist');
        cy.get('.sw-skeleton').should('not.exist');

        // go back to listing and edit rule again
        cy.get(page.elements.smartBarBack).click();

        cy.wait('@loadData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Rule 1st');
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Rule 1st');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // verify that selected conditions persisted
        conditions.forEach((name, index) => {
            cy.get('.sw-condition .sw-single-select__selection-text').eq(index).contains(name);
        });
    });

    it('@base @rule: check rule condition options filtered via rule config', { tags: ['pa-services-settings', 'quarantined'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule`,
            method: 'POST',
        }).as('saveData');

        cy.get('a[href="#/sw/settings/rule/create"]').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // fill basic data
        cy.get('input[name=sw-field--rule-name]').clearTypeAndCheck('Rule');
        cy.get('input[name=sw-field--rule-priority]').clearTypeAndCheck('100');

        // select condition type and operator
        cy.get('.sw-condition-type-select__select')
            .typeSingleSelectAndCheck(
                'Delivery status',
                '.sw-condition-type-select__select',
            );
        cy.get('.sw-condition .sw-condition-operator-select__select').should('exist');

        cy.get('.sw-condition-operator-select__select')
            .typeSingleSelectAndCheck(
                'Is one of',
                '.sw-condition-operator-select__select',
            );

        cy.get('.sw-condition .sw-entity-multi-select')
            .typeMultiSelectAndCheck('Open');

        cy.get('.sw-settings-rule-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
    });
});
