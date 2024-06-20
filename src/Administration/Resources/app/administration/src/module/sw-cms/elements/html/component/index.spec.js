import 'src/module/sw-cms/service/cms.service';
import '../index';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import './index';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-html', {
        sync: true,
    }), {
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
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
        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => {},
        });

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
