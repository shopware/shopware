import detailComponent from '../index';

describe('Experience Studio detail mutation errors', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it.each([
        [
            'CONTENT_SYSTEM__PROPERTY_NOT_MAPPABLE',
            'sw-experience-studio.detail.mappingMutationErrors.propertyNotMappable',
        ],
        [
            'CONTENT_SYSTEM__UNKNOWN_MAPPING_PATH',
            'sw-experience-studio.detail.mappingMutationErrors.unknownMappingPath',
        ],
        [
            'CONTENT_SYSTEM__MAPPING_TYPE_MISMATCH',
            'sw-experience-studio.detail.mappingMutationErrors.mappingTypeMismatch',
        ],
    ])('shows the translated mapping guidance for %s', (code, snippetKey) => {
        const createNotificationError = jest.fn();
        const vm = {
            createNotificationError,
            $t: jest.fn((key: string) => `translated:${key}`),
        };

        methods.notifyMutationError.call(vm, [code]);

        expect(vm.$t).toHaveBeenCalledWith(snippetKey);
        expect(createNotificationError).toHaveBeenCalledWith({
            message: `translated:${snippetKey}`,
        });
    });

    it('keeps the generic notification for unknown mutation errors', () => {
        const createNotificationError = jest.fn();
        const vm = {
            createNotificationError,
            $t: jest.fn(),
        };

        methods.notifyMutationError.call(vm, ['CONTENT_SYSTEM__UNRECOGNIZED']);

        expect(vm.$t).not.toHaveBeenCalled();
        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'The layout edit failed. Please try again.',
        });
    });
});
