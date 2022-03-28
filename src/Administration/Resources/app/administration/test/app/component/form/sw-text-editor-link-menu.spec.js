import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-editor/sw-text-editor-link-menu';

const seoDomainPrefix = '124c71d524604ccbad6042edce3ac799';
const prepareLinkDataProvider = [{
    value: 'aaaaaaa524604ccbad6042edce3ac799',
    type: 'detail',
    result: `${seoDomainPrefix}/detail/aaaaaaa524604ccbad6042edce3ac799#`,
}, {
    value: 'aaaaaaa524604ccbad6042edce3ac799',
    type: 'category',
    result: `${seoDomainPrefix}/navigation/aaaaaaa524604ccbad6042edce3ac799#`,
}, {
    value: 'test@shopware.com',
    type: 'email',
    result: 'mailto:test@shopware.com',
}, {
    value: '0123/4567890123',
    type: 'phone',
    result: 'tel:01234567890123',
}, {
    value: '+49123/4567890123',
    type: 'phone',
    result: 'tel:+491234567890123',
}, {
    value: 'www.domain.de/test',
    type: 'link',
    result: 'http://www.domain.de/test',
}, {
    value: 'domain.de',
    type: 'link',
    result: 'http://domain.de',
}, {
    value: 'http://domain.de',
    type: 'link',
    result: 'http://domain.de',
}];

let wrapper;

function createWrapper(buttonConfig = {}) {
    return shallowMount(Shopware.Component.build('sw-text-editor-link-menu'), {
        stubs: {
            'sw-button': true,
            'sw-switch-field': true,
            'sw-select-field': true,
            'sw-text-field': true,
            'sw-entity-single-select': true,
            'sw-category-tree-field': true,
            'sw-email-field': true,
        },
        propsData: {
            buttonConfig: {
                title: 'test',
                icon: '',
                expanded: true,
                newTab: true,
                displayAsButton: true,
                value: '',
                type: 'link',
                tag: 'a',
                active: false,
                ...buttonConfig,
            },
        }
    });
}

afterEach(() => {
    if (wrapper) {
        wrapper.destroy();
    }
});

describe('components/form/sw-text-editor/sw-text-editor-link-menu', () => {
    it('should be a Vue.js component', async () => {
        wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    prepareLinkDataProvider.forEach(({ value, type, result }) => {
        it(`should prepare link correctly: (type: ${type}; input: ${value})`, async () => {
            wrapper = createWrapper({
                value,
                type,
            });

            await wrapper.vm.$nextTick();
            expect(wrapper.vm.prepareLink()).toBe(result);
        });
    });
});
