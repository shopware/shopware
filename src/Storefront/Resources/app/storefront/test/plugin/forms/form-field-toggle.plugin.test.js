import FormFieldTogglePlugin from 'src/plugin/forms/form-field-toggle.plugin';

describe('FormFieldTogglePlugin', () => {
    let element;

    beforeEach(() => {
        const template = `
            <form id="register-form" action="/register" method="post">
                <input
                    data-form-field-toggle="true"
                    data-form-field-toggle-target=".js-form-field-toggle-target"
                    data-form-field-toggle-value="true"
                    type="checkbox"
                    name="company"
                    id="is-company"
                >

                <input
                    class="js-form-field-toggle-target d-none"
                    type="text"
                    name="company-name"
                >
            </form>

            <form id="newsletterForm" action="/newsletter" method="post">
                <select id="newsletterAction"
                        data-form-field-toggle="true"
                        data-form-field-toggle-target=".js-field-toggle-newsletter-additional"
                        data-form-field-toggle-value="subscribe"
                        data-form-field-toggle-button-target="[data-newsletter-submit-button]"
                        data-form-field-toggle-button-text="Unsubscribe">
                    <option value="subscribe" selected="selected">Subscribe</option>
                    <option value="unsubscribe">Unsubscribe</option>
                </select>

                <div class="js-field-toggle-newsletter-additional d-none">
                    <input id="firstName" type="text" required="required">
                </div>

                <button type="submit" data-newsletter-submit-button="true">Subscribe</button>
            </form>
        `;

        document.body.innerHTML = template;
        window.PluginManager.initializePlugins = jest.fn();

        element = document.querySelector('#is-company');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    function createCheckboxPlugin() {
        return new FormFieldTogglePlugin(element);
    }

    function createNewsletterPlugin() {
        const newsletterElement = document.querySelector('#newsletterAction');

        return new FormFieldTogglePlugin(newsletterElement);
    }

    test('creates plugin instance', () => {
        const plugin = createCheckboxPlugin();

        expect(plugin instanceof FormFieldTogglePlugin).toBe(true);
    });

    test('shows target when checkbox is checked', () => {
        createCheckboxPlugin();

        const checkbox = document.querySelector('#is-company');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change'));

        const target = document.querySelector('.js-form-field-toggle-target');

        expect(target.classList.contains('d-block')).toBe(true);
        expect(target.classList.contains('d-none')).toBe(false);
    });

    test('shows newsletter target and keeps subscribe button text by default', () => {
        createNewsletterPlugin();

        const toggleTarget = document.querySelector('.js-field-toggle-newsletter-additional');
        const submitButton = document.querySelector('[data-newsletter-submit-button]');
        const firstNameField = document.querySelector('#firstName');

        expect(toggleTarget.classList.contains('d-none')).toBe(false);
        expect(toggleTarget.classList.contains('d-block')).toBe(true);
        expect(firstNameField.hasAttribute('required')).toBe(true);
        expect(firstNameField.hasAttribute('disabled')).toBe(false);
        expect(submitButton.textContent.trim()).toBe('Subscribe');
    });

    test('hides newsletter target and switches to alternate button text when value does not match', () => {
        createNewsletterPlugin();

        const select = document.querySelector('#newsletterAction');
        select.value = 'unsubscribe';
        select.dispatchEvent(new Event('change'));

        const toggleTarget = document.querySelector('.js-field-toggle-newsletter-additional');
        const submitButton = document.querySelector('[data-newsletter-submit-button]');
        const firstNameField = document.querySelector('#firstName');

        expect(toggleTarget.classList.contains('d-none')).toBe(true);
        expect(toggleTarget.classList.contains('d-block')).toBe(false);
        expect(firstNameField.hasAttribute('required')).toBe(false);
        expect(firstNameField.hasAttribute('disabled')).toBe(true);
        expect(submitButton.textContent.trim()).toBe('Unsubscribe');
    });

    test('switches newsletter button back to default text when value matches again', () => {
        createNewsletterPlugin();

        const select = document.querySelector('#newsletterAction');

        select.value = 'unsubscribe';
        select.dispatchEvent(new Event('change'));

        select.value = 'subscribe';
        select.dispatchEvent(new Event('change'));

        const submitButton = document.querySelector('[data-newsletter-submit-button]');

        expect(submitButton.textContent.trim()).toBe('Subscribe');
    });
});
