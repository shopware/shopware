import { shallowMount, createLocalVue } from '@vue/test-utils';
import swDashboardStatistics from 'src/module/sw-dashboard/component/sw-dashboard-statistics';
import dictionary from 'src/module/sw-dashboard/snippet/en-GB.json';
import { currency } from 'src/core/service/utils/format.utils';

Shopware.Component.register('sw-dashboard-statistics', swDashboardStatistics);

async function createWrapper(privileges = [], orderSumToday = null) {
    const localVue = createLocalVue();
    localVue.filter('asset', v => v);
    localVue.filter('date', v => v);
    localVue.filter('currency', currency);

    const responseMock = [{}, {}];
    responseMock.aggregations = {
        order_count_bucket: {
            buckets: []
        },
        order_sum_bucket: {
            buckets: []
        }
    };

    const options = {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-chart-card': true,
            'sw-entity-listing': true,
            'sw-chart': true,
            'sw-select-field': true,
            'sw-skeleton': true,
            'sw-help-text': true,
        },
        mocks: {
            $tc: (...args) => JSON.stringify([...args]),
            $i18n: {
                locale: 'en-GB',
                messages: {
                    'en-GB': dictionary
                }
            }
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
        },
        computed: {
            systemCurrencyISOCode() {
                return 'EUR';
            },
            isSessionLoaded() {
                return true;
            }
        }
    };

    if (orderSumToday !== null) {
        options.computed.orderSumToday = () => orderSumToday;
    }

    return shallowMount(await Shopware.Component.build('sw-dashboard-statistics'), options);
}

describe('module/sw-dashboard/component/sw-dashboard-statistics', () => {
    let wrapper = null;

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
        jest.useFakeTimers('modern');
    });

    afterEach(() => {
        wrapper.destroy();
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not show the stats', async () => {
        wrapper = await createWrapper();

        const orderToday = wrapper.find('.sw-dashboard-statistics__intro-stats-today');
        const statisticsCount = wrapper.find('.sw-dashboard-statistics__statistics-count');
        const statisticsSum = wrapper.find('.sw-dashboard-statistics__statistics-sum');

        expect(orderToday.exists()).toBeFalsy();
        expect(statisticsCount.exists()).toBeFalsy();
        expect(statisticsSum.exists()).toBeFalsy();
    });

    it('should show the stats', async () => {
        wrapper = await createWrapper(['order.viewer']);
        await flushPromises();

        const orderToday = wrapper.find('.sw-dashboard-statistics__intro-stats-today');
        const statisticsCount = wrapper.find('.sw-dashboard-statistics__statistics-count');
        const statisticsSum = wrapper.find('.sw-dashboard-statistics__statistics-sum');

        expect(orderToday.exists()).toBeTruthy();
        expect(statisticsCount.exists()).toBeTruthy();
        expect(statisticsSum.exists()).toBeTruthy();
    });

    it('should not exceed decimal places of two', async () => {
        wrapper = await createWrapper(['order.viewer'], 43383.13234554);
        await flushPromises();

        const todaysTotalSum = wrapper.find('.sw-dashboard-statistics__intro-stats-today-single-stat:nth-of-type(2) span:nth-of-type(2)').text();
        expect(todaysTotalSum).toBe('€43,383.13');
    });
});
