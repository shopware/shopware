import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/base/sw-collapse';
import 'src/app/component/sidebar/sw-sidebar-collapse';

function createWrapper(propsData = {}) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-sidebar-collapse'), {
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
        propsData: {
            ...propsData
        },
        provide: {
            cmsElementFavorites: {
                isFavorite() {
                    return false;
                }
            }
        },
        mocks: {
            $tc: (snippetPath, count, values) => snippetPath + count + JSON.stringify(values)
        }
    });
}

describe('src/app/component/sidebar/sw-sidebar-collapse', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {});

    beforeEach(() => {});

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    describe('no props', () => {
        it('should be a Vue.JS component', async () => {
            wrapper = await createWrapper({});

            expect(wrapper.vm).toBeTruthy();
        });

        it('has a chevron pointing right', async () => {
            wrapper = await createWrapper({});

            expect(wrapper.find('.sw-sidebar-collapse__button').text()).toContain('right');
        });
    });

    describe('prop expandChevronDirection down', () => {
        it('has a chevron pointing down', async () => {
            wrapper = await createWrapper({
                expandChevronDirection: 'bottom'
            });

            expect(wrapper.find('.sw-sidebar-collapse__button').text()).toContain('bottom');
        });
    });
});
