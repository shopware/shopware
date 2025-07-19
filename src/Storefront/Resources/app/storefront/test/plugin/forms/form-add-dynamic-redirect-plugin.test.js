import FormAddDynamicRedirectPlugin from 'src/plugin/forms/form-add-dynamic-redirect-plugin';

const template = `
    <form action="/some/url"
        method="post"
        data-form-add-dynamic-redirect="true">

        <input type="text" name="test">
    </form>
`;

describe('FormAddDynamicRedirectPlugin tests', () => {
    let formAddDynamicRedirectPlugin;

    beforeEach(async () => {
        FormAddDynamicRedirectPlugin.options.redirectTo = 'frontend.detail.page';
        FormAddDynamicRedirectPlugin.options.redirectParameter = '{"productId": "0194da86d1cc70beb15bc0660882b87a"}';

        document.body.innerHTML = template;
        const element = document.querySelector('[data-form-add-dynamic-redirect]');

        formAddDynamicRedirectPlugin = new FormAddDynamicRedirectPlugin(element);
    });

    test('plugin instance is created', () => {
        expect(formAddDynamicRedirectPlugin).toBeInstanceOf(FormAddDynamicRedirectPlugin);
    });

    test('should add redirect parameters to form', () => {
        const form = document.querySelector('form');
        const submitEvent = new Event('submit', { bubbles: true });

        form.dispatchEvent(submitEvent);

        const redirectToInput = form.querySelector('input[name="redirectTo"]');
        const redirectParameterInput = form.querySelector('input[name="redirectParameters[productId]"]');

        expect(redirectToInput.value).toBe('frontend.detail.page');
        expect(redirectParameterInput.value).toBe('0194da86d1cc70beb15bc0660882b87a');
    });
});
