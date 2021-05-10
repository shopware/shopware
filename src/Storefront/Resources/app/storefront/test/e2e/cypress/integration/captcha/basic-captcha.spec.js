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
    modalButtonDismiss: 'button[data-dismiss="modal"]',
    basicCaptcha: '.basic-captcha',
    basicCaptchaImage: '.basic-captcha-content-image',
    basicCaptchaRefreshIcon: '.basic-captcha-content-refresh-icon',
    formBasicCaptcha: '.basic-captcha input[name="shopware_basic_captcha_confirm"]',
    alertErrors: '.alert.alert-danger.alert-has-icon'
}

describe('Basic captcha', () => {
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

    function mockConfig() {
        return cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.basicInformation.activeCaptchasV2': {
                            'basicCaptcha': {
                                'isActive': true,
                                'name': 'basicCaptcha'
                            }
                        }
                    }
                }
            };

            return cy.request(requestConfig);
        });
    }

    function fillForm() {
        cy.get(selector.formContact).within(() => {
            cy.get(selector.formContactSalutation).select('Not specified');
            cy.get(selector.formContactFirstName).type('Ky');
            cy.get(selector.formContactLastName).type('Le');
            cy.get(selector.formContactMail).type('kyln@shopware.com');
            cy.get(selector.formContactPhone).type('+123456789');
            cy.get(selector.formContactSubject).type('Captcha');
            cy.get(selector.formContactComment).type('Basic Captcha.');
            cy.get(selector.formBasicCaptcha).type('kyln');
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

    beforeEach(() => {
        mockConfig().then(() => openContactForm());
    });

    it('Should be visible basic captcha', () => {
        cy.get(selector.basicCaptcha).should('be.visible');
        cy.get(selector.basicCaptchaImage).should('be.visible');
        cy.get(selector.basicCaptchaRefreshIcon).should('be.visible');

        fillForm();

        submitContactForm((response) => {
            expect(response).to.have.property('status', 200);
        });

        cy.get(selector.alertErrors).should('be.visible');
    })
})
