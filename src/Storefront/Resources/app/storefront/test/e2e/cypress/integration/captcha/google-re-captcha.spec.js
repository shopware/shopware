// https://developers.google.com/recaptcha/docs/faq
const reCAPTCHA_TEST_SITEKEY = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';

function setActiveCaptchas(value) {
    return cy.authenticate().then((result) => {
        const requestConfig = {
            headers: {
                Authorization: `Bearer ${result.access}`
            },
            method: 'post',
            url: `api/_action/system-config/batch`,
            body: {
                null: {
                    'core.basicInformation.activeCaptchasV2': value,
                }
            }
        };

        return cy.request(requestConfig);
    });
}

describe('Captcha: Google ReCaptcha', () => {
    beforeEach(() => {
        cy.setCookie('_GRECAPTCHA', '1');
    });

    it('@captcha: grecaptcha is loaded when V2 or V3 activated', () => {
        setActiveCaptchas({
            'googleReCaptchaV2': {
                'isActive': false,
            },
            'googleReCaptchaV3': {
                'isActive': false,
            }
        });

        cy.visit('/');

        cy.window().then((win) => {
            cy.expect(win.googleReCaptchaV2Active).to.equal(undefined);
            cy.expect(win.googleReCaptchaV3Active).to.equal(undefined);
            cy.expect(win.grecaptcha).to.equal(undefined);
        });

        setActiveCaptchas({
            'googleReCaptchaV2': {
                'isActive': true,
            }
        });

        cy.visit('/');

        cy.window().then((win) => {
            cy.expect(win.googleReCaptchaV2Active).to.equal(true);
            cy.expect(win.grecaptcha).to.not.empty;
        });

        setActiveCaptchas({
            'googleReCaptchaV3': {
                'isActive': true,
            }
        });

        cy.visit('/');

        cy.window().then((win) => {
            cy.expect(win.googleReCaptchaV3Active).to.equal(true);
            cy.expect(win.grecaptcha).to.not.empty;
        });
    });

    it('@captcha: register form show google captcha v2 checkbox', () => {
        setActiveCaptchas({
            googleReCaptchaV2: {
                name: 'googleReCaptchaV2',
                isActive: true,
                config: {
                    siteKey: reCAPTCHA_TEST_SITEKEY,
                    invisible: false
                }
            }
        });

        cy.visit('/account/login');

        cy.window().then((win) => {
            cy.get('.grecaptcha-v2-container iframe').should('be.visible');
            cy.get('.grecaptcha-v2-input').should('be.exist');
            cy.get('.grecaptcha-v2-input').should('be.not.visible');
        })
    });

    it('@captcha: contact form show google captcha v2 checkbox', () => {
        setActiveCaptchas({
            googleReCaptchaV2: {
                name: 'googleReCaptchaV2',
                isActive: true,
                config: {
                    siteKey: reCAPTCHA_TEST_SITEKEY,
                    invisible: false
                }
            },
            googleReCaptchaV3: {
                name: 'googleReCaptchaV3',
                isActive: true,
                config: {
                    siteKey: reCAPTCHA_TEST_SITEKEY
                }
            }
        });

        cy.visit('/');

        cy.window().then((win) => {
            const contactForm = cy.get('.modal');
            contactForm.should('be.not.visible');
            cy.get('.footer-contact-form a').click();
            contactForm.should('be.visible');

            contactForm.get('.grecaptcha-v2-container iframe').should('be.visible');
            contactForm.get('.grecaptcha-v2-input').should('be.exist');
            contactForm.get('.grecaptcha-v2-input').should('be.not.visible');
            contactForm.get('.grecaptcha-protection-information').should('be.visible');
            contactForm.get('.grecaptcha-protection-information').should('have.length', 1);
            contactForm.get('.grecaptcha-protection-information').should('include.text', 'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.');
        })
    });

    it('@captcha: register form show google captcha v2 invisible and v3', () => {
        setActiveCaptchas({
            googleReCaptchaV2: {
                name: 'googleReCaptchaV2',
                isActive: true,
                config: {
                    siteKey: reCAPTCHA_TEST_SITEKEY,
                    invisible: true,
                }
            }
        });

        cy.visit('/account/login');

        cy.window().then((win) => {
            cy.get('.grecaptcha-v2-container').should('be.exist');
            cy.get('.grecaptcha-v2-container').should('be.not.visible');
            cy.get('.grecaptcha-badge').should('be.exist');
            cy.get('.grecaptcha-badge').should('be.not.visible');
            cy.get('.grecaptcha-protection-information').should('be.visible');
            cy.get('.grecaptcha-protection-information').should('have.length', 1);
            cy.get('.grecaptcha-protection-information').should('include.text', 'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.');
        });

        setActiveCaptchas({
            googleReCaptchaV3: {
                name: 'googleReCaptchaV3',
                isActive: true,
                config: {
                    siteKey: reCAPTCHA_TEST_SITEKEY,
                }
            }
        });

        cy.visit('/account/login');

        cy.window().then((win) => {
            cy.get('.grecaptcha-protection-information').should('be.visible');
            cy.get('.grecaptcha-badge').should('be.exist');
            cy.get('.grecaptcha-badge').should('be.not.visible');
            cy.get('.grecaptcha_v3-input').should('be.exist');
            cy.get('.grecaptcha_v3-input').should('be.not.visible');
            cy.get('.grecaptcha-protection-information').should('have.length', 1);
            cy.get('.grecaptcha-protection-information').should('include.text', 'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.');
        });

        setActiveCaptchas({
            googleReCaptchaV2: {
                name: 'googleReCaptchaV2',
                isActive: true,
                config: {
                    siteKey: reCAPTCHA_TEST_SITEKEY,
                    invisible: true
                }
            },
            googleReCaptchaV3: {
                name: 'googleReCaptchaV3',
                isActive: true,
                config: {
                    siteKey: reCAPTCHA_TEST_SITEKEY,
                }
            }
        });

        cy.visit('/account/login');

        cy.window().then((win) => {
            cy.get('.grecaptcha-v2-container').should('be.not.visible');
            cy.get('.grecaptcha-protection-information').should('be.visible');
            // only one .grecaptcha-protection-information exists
            cy.get('.grecaptcha-protection-information').should('have.length', 1);
            cy.get('.grecaptcha-protection-information').should('include.text', 'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.');
        })
    });
});
