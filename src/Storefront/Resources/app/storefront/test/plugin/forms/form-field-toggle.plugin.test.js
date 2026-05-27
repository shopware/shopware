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
        `;

        document.body.innerHTML = template;
        element = document.querySelector('[data-form-field-toggle]');
    });

    test('creates plugin instance', () => {
        const plugin = new FormFieldTogglePlugin(element);
        expect(typeof plugin).toBe('object');
    });

    test('shows target when checkbox is checked', () => {
        new FormFieldTogglePlugin(element);

        // Tick the checkbox
        const checkbox = document.querySelector('#is-company');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change'));

        // Check if the target is shown
        const target = document.querySelector('.js-form-field-toggle-target');
        expect(target.classList.contains('d-block')).toBe(true);
        expect(target.classList.contains('d-none')).toBe(false);
    });
});