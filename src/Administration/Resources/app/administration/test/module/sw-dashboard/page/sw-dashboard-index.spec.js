import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-dashboard/page/sw-dashboard-index';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.filter('asset', v => v);
    localVue.filter('date', v => v);
    localVue.filter('currency', v => v);

    const responseMock = [{}, {}];
    responseMock.aggregations = {
        order_count_month: {
            buckets: []
        }
    };

    return shallowMount(Shopware.Component.build('sw-dashboard-index'), {
        localVue,
        stubs: {
            'sw-page': true,
            'sw-card': true,
            'sw-card-view': true,
            'sw-dashboard-external-link': true,
            'sw-container': true,
            'sw-button': true,
            'sw-entity-listing': true,
            'sw-chart': true,
            'sw-icon': true
        },
        mocks: {
            $tc: (...args) => JSON.stringify([...args])
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve(responseMock)
                })
            },
            stateStyleDataProviderService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        }
    });
}

describe('module/sw-dashboard/page/sw-dashboard-index', () => {
    let wrapper = createWrapper();

    beforeAll(() => {
        Shopware.State.registerModule('session', {
            state: {
                currentUser: null
            },
            mutations: {
                setCurrentUser(state, user) {
                    state.currentUser = user;
                }
            }
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should not show the stats', async () => {
        const orderToday = wrapper.find('.sw-dashboard-index__intro-stats-today');
        const statisticsCount = wrapper.find('.sw-dashboard-index__statistics-count');
        const statisticsSum = wrapper.find('.sw-dashboard-index__statistics-sum');

        expect(orderToday.exists()).toBeFalsy();
        expect(statisticsCount.exists()).toBeFalsy();
        expect(statisticsSum.exists()).toBeFalsy();
    });

    it('should show the stats', async () => {
        wrapper = await createWrapper(['order.viewer']);
        await wrapper.vm.$nextTick();

        const orderToday = wrapper.find('.sw-dashboard-index__intro-stats-today');
        const statisticsCount = wrapper.find('.sw-dashboard-index__statistics-count');
        const statisticsSum = wrapper.find('.sw-dashboard-index__statistics-sum');

        expect(orderToday.exists()).toBeTruthy();
        expect(statisticsCount.exists()).toBeTruthy();
        expect(statisticsSum.exists()).toBeTruthy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display headline for unknown user', async () => {
        expect(wrapper.text()).toContain('sw-dashboard.introduction.headlineUnkownUser');
    });

    it('should display users firstName', async () => {
        Shopware.State.commit('setCurrentUser', {
            firstName: 'userFirstName'
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('{"greetingName":"userFirstName"}');
    });

    it('should display users "username"', async () => {
        Shopware.State.commit('setCurrentUser', {
            username: 'username'
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('{"greetingName":"username"}');
    });
});
