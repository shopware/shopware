import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

const customFields = [
    {
        customFields: [
            {
                config: {
                    customFieldPosition: 1,
                },
                id: '5af5c4492a204b17a7e800d94425fe0c',
            },
            {
                config: {
                    customFieldPosition: 4,
                },
                id: 'de8de156da134dabac24257f81ff282f',
            },
            {
                config: {
                    customFieldPosition: 6,
                },
                id: 'e33027523c86413c8018f75de49be56f',
            },
            {
                config: {
                    customFieldPosition: 9,
                },
                id: 'f95226379abf48ceb3129de7f266d293',
            },
            {
                config: {
                    customFieldPosition: 22,
                },
                id: '8bc279512c6e4f40afe410264b266c12',
            },
            {
                config: {
                    customFieldPosition: 45,
                },
                id: '3497634a5336477597586e9618c0ca4f',
            },
        ],
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-customer-detail-base', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(customFields),
                            get: () => Promise.resolve({ id: '' }),
                        };
                    },
                },
            },

            stubs: {
                'sw-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-customer-card': {
                    template: '<div></div>',
                },
                'sw-custom-field-set-renderer': await wrapTestComponent('sw-custom-field-set-renderer', { sync: true }),
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer', { sync: true }),
                'sw-field': {
                    template: '<div></div>',
                },
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-customer-base-info': true,
                'sw-customer-default-addresses': true,
                'sw-skeleton': true,
                'sw-button-process': true,
                'sw-media-collapse': true,
                'sw-icon': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-inheritance-switch': true,
                'sw-help-text': true,
            },
        },

        props: {
            customerEditMode: false,
            customer: {},
        },
    });
}

describe('module/sw-customer/view/sw-customer-detail-base.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort custom fields by their position', async () => {
        const formFields = wrapper.findAll('.sw-form-field-renderer');

        expect(formFields).toHaveLength(6);

        const [
            first,
            second,
            third,
            fourth,
            fifth,
            sixth,
        ] = formFields;

        expect(first.attributes('customfieldposition')).toBe('1');
        expect(second.attributes('customfieldposition')).toBe('4');
        expect(third.attributes('customfieldposition')).toBe('6');
        expect(fourth.attributes('customfieldposition')).toBe('9');
        expect(fifth.attributes('customfieldposition')).toBe('22');
        expect(sixth.attributes('customfieldposition')).toBe('45');
    });
});
