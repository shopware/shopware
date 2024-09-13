/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-html', {
        sync: true,
    }), {
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-code-editor': true,
            },
        },
        props: {
            element: {
                config: {
                    content: {
                        value: '<div><h1>Test</h1></div>',
                    },
                },
            },
        },
    });
}

describe('src/module/sw-cms/elements/html/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('renders the value in the HTML editor', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.html()).toContain('<div><h1>Test</h1></div>');
    });
});
