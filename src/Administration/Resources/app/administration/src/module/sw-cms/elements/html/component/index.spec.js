/**
 * @package buyers-experience
 * @group disabledCompat
 */

import 'src/module/sw-cms/service/cms.service';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { mount } from '@vue/test-utils';

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

describe('src/module/sw-cms/elements/html/component/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('renders the value in the HTML editor', () => {
        expect(wrapper.html()).toContain('<div><h1>Test</h1></div>');
    });
});
