import FormFieldTogglePlugin from 'src/plugin/forms/form-field-toggle.plugin';

/**
 * @package content
 */
describe('Form field toggle plugin', () => {
    const template = `
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

    function createPlugin() {
        const element = document.querySelector('#newsletterAction');

        return new FormFieldTogglePlugin(element);
    }

    beforeEach(() => {
        document.body.innerHTML = template;
        window.PluginManager.initializePlugins = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('should instantiate plugin', () => {
        const plugin = createPlugin();

        expect(plugin instanceof FormFieldTogglePlugin).toBe(true);
    });

    it('should show target and keep subscribe button text by default', () => {
        createPlugin();

        const toggleTarget = document.querySelector('.js-field-toggle-newsletter-additional');
        const submitButton = document.querySelector('[data-newsletter-submit-button]');
        const firstNameField = document.querySelector('#firstName');

        expect(toggleTarget.classList.contains('d-none')).toBe(false);
        expect(toggleTarget.classList.contains('d-block')).toBe(true);
        expect(firstNameField.hasAttribute('required')).toBe(true);
        expect(firstNameField.hasAttribute('disabled')).toBe(false);
        expect(submitButton.textContent.trim()).toBe('Subscribe');
    });

    it('should hide target and switch to alternate button text when value does not match', () => {
        createPlugin();

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

    it('should switch back to default button text when value matches again', () => {
        createPlugin();

        const select = document.querySelector('#newsletterAction');

        select.value = 'unsubscribe';
        select.dispatchEvent(new Event('change'));
        select.value = 'subscribe';
        select.dispatchEvent(new Event('change'));

        const submitButton = document.querySelector('[data-newsletter-submit-button]');

        expect(submitButton.textContent.trim()).toBe('Subscribe');
    });
});
