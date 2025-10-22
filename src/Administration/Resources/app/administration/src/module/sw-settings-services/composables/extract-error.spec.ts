import extractError from './extract-error';

describe('src/module/sw-settings-services/composables/extract-error.ts', () => {
    class MockAxiosError extends Error {
        get name() {
            return 'AxiosError';
        }

        get response() {
            return {
                data: {
                    errors: [{ detail: 'API error' }],
                },
            };
        }
    }

    it.each([
        [
            new Error('Test error'),
            'Test error',
        ],
        [
            new MockAxiosError(),
            'API error',
        ],
        [
            { response: { data: {} } },
            'unknown error',
        ],
        [
            null,
            'unknown error',
        ],
        [
            'string error',
            'unknown error',
        ],
    ])('extracts the correct error message', (exception, expected) => {
        expect(extractError(exception)).toBe(expected);
    });
});
