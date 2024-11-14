/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { kebabCase } from 'lodash';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const expectedProps = {
    cssClass: 'nice-classes has--nice-modifier another-class',
    marginTop: '2px',
    marginBottom: '4px',
    marginLeft: '6px',
    marginRight: '8px',
};
const block = {
    name: 'Block name',
    ...expectedProps,
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-block-layout-config', {
            sync: true,
        }),
        {
            attachTo: document.body,
            props: {
                block,
            },
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
                stubs: {
                    'sw-text-field': {
                        template:
                            '<input class="sw-text-field" :value="value" @input="$emit(\'update:value\', $event.target.value)" />',
                        props: ['value'],
                    },
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-block-layout-config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it.each(Object.entries(expectedProps))('should be able to use the provided %s block data', async (property, value) => {
        const wrapper = await createWrapper();
        const selector = `.sw-cms-block-layout-config__${kebabCase(property)}`;

        expect(wrapper.get(selector)).toBeTruthy();
        expect(wrapper.get(selector).attributes('value')).toBe(value);
    });
});
