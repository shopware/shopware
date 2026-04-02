/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard-csv-page';

function createProfile() {
    return {
        sourceEntity: 'product',
        delimiter: ';',
        enclosure: '"',
        mapping: [],
    };
}

async function createWrapper(importExport = { getMappingFromTemplate: jest.fn() }) {
    const profile = createProfile();

    const wrapper = mount(
        await wrapTestComponent('sw-import-export-new-profile-wizard-csv-page', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-file-input': await wrapTestComponent('sw-file-input', { sync: true }),
                },
                provide: {
                    repositoryFactory: {},
                    importExport: {
                        getMappingFromTemplate: jest.fn(),
                    },
                },
            },
            props: {
                profile,
            },
        },
    );

    wrapper.vm.importExport = importExport;

    return {
        wrapper,
        profile,
        importExport,
    };
}

function fileMock(input, file) {
    let fileInputValue = file.name;

    Object.defineProperty(input.element, 'files', {
        configurable: true,
        get: () => [file],
    });

    Object.defineProperty(input.element, 'value', {
        configurable: true,
        get: () => fileInputValue,
        set: (value) => {
            fileInputValue = value;
        },
    });
}

describe('module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard-csv-page', () => {
    it('should pass all supported file extensions to the file input', async () => {
        const { wrapper } = await createWrapper();

        const fileInput = wrapper.findComponent('.sw-import-export-new-profile-wizard-csv-page__file-upload');

        expect(fileInput.props('allowedFileExtensions')).toEqual([
            'csv',
        ]);
    });

    it('should import the mapping when the uploaded file has csv extension', async () => {
        const importExport = {
            getMappingFromTemplate: jest.fn().mockResolvedValue([
                {
                    key: 'id',
                    mappedKey: 'id',
                },
            ]),
        };

        const { wrapper, profile } = await createWrapper(importExport);
        const input = wrapper.find('.sw-import-export-new-profile-wizard-csv-page__file-upload .sw-file-input__file-input');
        const file = {
            name: 'products.test.csv',
            size: 123,
        };

        fileMock(input, file);

        await input.trigger('change');
        await flushPromises();

        expect(importExport.getMappingFromTemplate).toHaveBeenCalledWith(file, 'product', ';', '"');

        expect(profile.mapping).toEqual([
            {
                key: 'id',
                mappedKey: 'id',
            },
        ]);

        expect(wrapper.emitted()['next-allow']).toBeTruthy();
    });

    it('should not import the mapping when the uploaded file extension is not allowed', async () => {
        const importExport = {
            getMappingFromTemplate: jest.fn(),
        };

        const { wrapper, profile } = await createWrapper(importExport);
        const fileInput = wrapper.findComponent('.sw-import-export-new-profile-wizard-csv-page__file-upload');
        const input = wrapper.find('.sw-import-export-new-profile-wizard-csv-page__file-upload .sw-file-input__file-input');
        const invalidFile = {
            name: 'products.pdf',
            size: 123,
        };

        fileInput.vm.createNotificationError = jest.fn();
        fileMock(input, invalidFile);

        await input.trigger('change');
        await flushPromises();

        expect(importExport.getMappingFromTemplate).not.toHaveBeenCalled();
        expect(profile.mapping).toEqual([]);
        expect(wrapper.emitted()['next-allow']).toBeUndefined();
        expect(fileInput.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-file-input.notification.invalidFileExtension.message',
            title: 'global.default.error',
        });
    });
});
