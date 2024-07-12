/* eslint-disable */
import FormCmsHandlerPlugin from 'src/plugin/forms/form-cms-handler.plugin';

describe('Form CMS Handler tests', () => {

    let formCmsHandlerPlugin = undefined;
    let formElement = undefined;
    let mockHttpClient = undefined;

    beforeEach(() => {
        formElement = document.createElement('form');
        formCmsHandlerPlugin = new FormCmsHandlerPlugin(formElement);

        mockHttpClient = {post: jest.fn()};
        formCmsHandlerPlugin._client = mockHttpClient;
    });

    test('form cms handler plugin exists', () => {
        expect(typeof formCmsHandlerPlugin).toBe('object');
    });

    test('form cms handler resets form after successful ajax submission', () => {
        const resetSpy = jest.spyOn(formElement, 'reset');

        formElement.dispatchEvent(new Event('submit'));

        expect(mockHttpClient.post).toHaveBeenCalled();
        expect(resetSpy).toHaveBeenCalled();
    });
});
