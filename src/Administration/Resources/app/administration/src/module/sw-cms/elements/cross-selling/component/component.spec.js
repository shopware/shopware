/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElement = {
    data: {
        product: {
            crossSellings: [],
        },
    },
    config: {
        elMinWidth: {
            value: '300px',
        },
        boxLayout: {
            value: 'standard',
        },
        displayMode: {
            value: 'standard',
        },
    },
};

async function createWrapper(element = defaultElement) {
    return mount(await wrapTestComponent('sw-cms-el-cross-selling', {
        sync: true,
    }), {
        props: {
            element,
        },
        global: {
            stubs: {
                'sw-cms-el-product-box': true,
                'sw-icon': true,
            },
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
        },
    });
}

describe('module/sw-cms/elements/cross-selling/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/cross-selling');
    });

    afterEach(() => {
        Shopware.Store.get('cmsPageState').resetCmsPageState();
    });

    it('getProductEl applies props to the config object', async () => {
        const wrapper = await createWrapper();
        const product = {
            id: 'foo-bar',
        };

        const elementConfig = wrapper.vm.getProductEl(product);

        expect(elementConfig.data).toMatchObject({
            product,
        });
        expect(elementConfig.config).toMatchObject({
            boxLayout: {
                source: 'static',
                value: 'standard',
            },
            displayMode: {
                source: 'static',
                value: 'standard',
            },
        });
    });
});
