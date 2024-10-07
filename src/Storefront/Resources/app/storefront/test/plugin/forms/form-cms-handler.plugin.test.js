/* eslint-disable */
import FormCmsHandlerPlugin from 'src/plugin/forms/form-cms-handler.plugin';

const template = `
    <div class="cms-block">
      <form id="test-form"></form>
    <div>
`.trim();

describe('Form CMS Handler tests', () => {

    let formCmsHandlerPlugin = undefined;
    let formElement = undefined;

    beforeEach(() => {
        document.body.innerHTML = template;
        formElement = document.getElementById('test-form');
        formElement.parentElement.scrollIntoView = jest.fn(); // Used by form-cms-handler plugin, but not implemented by jsdom.
        formCmsHandlerPlugin = new FormCmsHandlerPlugin(formElement);
    });

    function setupMockHttpClient(responseContent) {
        const mockHttpClient = {post: jest.fn((url, data, callback) => callback(responseContent))};
        formCmsHandlerPlugin._client = mockHttpClient;

        return mockHttpClient;
    }

    test('form cms handler plugin exists', () => {
        expect(typeof formCmsHandlerPlugin).toBe('object');
    });

    test('form cms handler resets form after successful ajax submission', () => {
        const resetSpy = jest.spyOn(formElement, 'reset');

        const mockHttpClient = setupMockHttpClient('[{"type":"success","alert":""}]');

        formElement.dispatchEvent(new Event('submit'));

        expect(mockHttpClient.post).toHaveBeenCalled();
        expect(resetSpy).toHaveBeenCalled();
    });

    test('form cms handler does not reset after unsuccessful ajax submission', () => {
        const resetSpy = jest.spyOn(formElement, 'reset');

        const mockHttpClient = setupMockHttpClient('[{"type":"danger","alert":""}]');

        formElement.dispatchEvent(new Event('submit'));

        expect(mockHttpClient.post).toHaveBeenCalled();
        expect(resetSpy).not.toHaveBeenCalled();
    });
});
