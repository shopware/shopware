/**
 * @package buyers-experience
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
});
