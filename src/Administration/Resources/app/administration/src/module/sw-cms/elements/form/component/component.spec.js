/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(formType = null) {
    return mount(await wrapTestComponent('sw-cms-el-form', { sync: true }), {
        props: {
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: null,
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                    type: {
                        source: 'static',
                        value: formType,
                    },
                },
            },
            defaultConfig: {},
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
        },
    });
}

const formTemplates = [
    'form-contact',
    'form-newsletter',
];

describe('module/sw-cms/elements/form/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it.each(formTemplates)('should render form of type "%s"', async (type) => {
        expect((await createWrapper(type)).get(type)).toBeTruthy();
    });

    it('should return correct selectedForm for type "contact"', async () => {
        const wrapper = await createWrapper('contact');
        expect(wrapper.vm.selectedForm).toBe('sw-cms-el-form-template-contact');
    });

    it('should return correct selectedForm for type "newsletter"', async () => {
        const wrapper = await createWrapper('newsletter');
        expect(wrapper.vm.selectedForm).toBe('sw-cms-el-form-template-newsletter');
    });

    it('should return the type value for unknown types', async () => {
        const wrapper = await createWrapper('custom-type');
        expect(wrapper.vm.selectedForm).toBe('custom-type');
    });
});
