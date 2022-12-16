import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/base/sw-collapse';
import 'src/app/component/sidebar/sw-sidebar-collapse';

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-sidebar-collapse'), {
        localVue,
        stubs: {
            'sw-icon': {
                props: [
                    'name'
                ],
                template: '<span>{{ name }}</span>'
            },
            'sw-collapse': true
        },
        mocks: {
            $tc: (snippetPath, count, values) => snippetPath + count + JSON.stringify(values)
        }
    });
}

describe('src/app/component/sidebar/sw-sidebar-collapse', () => {
    describe('no props', () => {
        it('has a chevron pointing right', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.find('.sw-sidebar-collapse__button').text()).toContain('right');
        });
    });

    describe('prop expandChevronDirection down', () => {
        it('has a chevron pointing down', async () => {
            const wrapper = await createWrapper();

            await wrapper.setProps({
                expandChevronDirection: 'bottom'
            });

            expect(wrapper.find('.sw-sidebar-collapse__button').text()).toContain('bottom');
        });
    });
});
