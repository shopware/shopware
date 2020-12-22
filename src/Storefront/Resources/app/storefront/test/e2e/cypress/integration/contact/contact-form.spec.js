const selector = {
    footerLinkContact: '.footer-contact-form a[data-toggle="modal"]',
    formContact: 'form[action="/form/contact"]',
    formContactSalutation: '#form-Salutation',
    formContactFirstName: '#form-firstName',
    formContactLastName: '#form-lastName',
    formContactMail: '#form-email',
    formContactPhone: '#form-phone',
    formContactSubject: '#form-subject',
    formContactComment: '#form-comment',
    formContactDataProtectionCheckbox: '.privacy-notice input[type="checkbox"]',
    formContactButtonSubmit: 'button[type="submit"]',
    modalButtonDismiss: 'button[data-dismiss="modal"]'
}

describe('Contact form', () => {
    function openContactForm(callback) {
        cy.visit('/');

        cy.server();
        cy.route({
            url: '/widgets/cms/*',
            method: 'GET'
        }).as('contactFormRequest');

        cy.get(selector.footerLinkContact).click();

        cy.wait('@contactFormRequest').then(() => {
            cy.get(selector.modalButtonDismiss).should('be.visible');

            if (typeof callback === 'function') {
                callback(arguments);
            }
        });
    }

    function fillOutContactForm() {
        cy.get(selector.formContact).within(() => {
            cy.get(selector.formContactSalutation).select('Not specified');
            cy.get(selector.formContactFirstName).type('Foo');
            cy.get(selector.formContactLastName).type('Bar');
            cy.get(selector.formContactMail).type('user@example.com');
            cy.get(selector.formContactPhone).type('+123456789');
            cy.get(selector.formContactSubject).type('Lorem ipsum');
            cy.get(selector.formContactComment).type('Dolor sit amet.');
            cy.get(selector.formContactDataProtectionCheckbox).check({force: true});
        });
    }

    function submitContactForm(callback) {
        cy.server();
        cy.route({
            url: '/form/contact',
            method: 'POST'
        }).as('contactFormPostRequest');

        cy.get(selector.formContact).within(() => {
            cy.get(selector.formContactButtonSubmit).click();
        });

        cy.wait('@contactFormPostRequest').then(callback);
    }

    function checkForCorrectlyLabelledPrivacyInformationCheckbox() {
        cy.get(selector.formContact).within(() => {
            cy.get(selector.formContactDataProtectionCheckbox).invoke('attr', 'id')
                .then((id) => {
                    cy.get(`label[for="${id}"]`).should('be.visible');
                });
        });
    }

    before(() => {
        openContactForm();
    });

    it('@contact: Should be possible to fill out and submit the contact form', () => {
        /**
         * This is a regression test for NEXT-12092.
         *
         * @see https://issues.shopware.com/issues/NEXT-12092
         */
        checkForCorrectlyLabelledPrivacyInformationCheckbox();

        fillOutContactForm();

        submitContactForm((response) => {
            expect(response).to.have.property('status', 200);
        });
    });
});
