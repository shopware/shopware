import { shallowMount, createLocalVue } from '@vue/test-utils';
import swDashboardStatistics from 'src/module/sw-dashboard/component/sw-dashboard-statistics';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-chart-card';
import 'src/app/component/base/sw-card';
import dictionary from 'src/module/sw-dashboard/snippet/en-GB.json';
import { currency } from 'src/core/service/utils/format.utils';

Shopware.Component.register('sw-dashboard-statistics', swDashboardStatistics);

async function createWrapper(privileges = [], orderSumToday = null) {
    const localVue = createLocalVue();
    localVue.filter('asset', v => v);
    localVue.filter('date', v => v);
    localVue.filter('currency', currency);

    const responseMock = {
        aggregations: {
            order_count_bucket: {
                buckets: [],
            },
            order_sum_bucket: {
                buckets: [],
            },
        },
    };

    const options = {
        localVue,
        stubs: {
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-chart-card': await Shopware.Component.build('sw-chart-card'),
            'sw-entity-listing': true,
            'sw-chart': true,
            'sw-select-field': await Shopware.Component.build('sw-select-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-skeleton': true,
            'sw-help-text': true,
            'sw-ignore-class': true,
            'sw-extension-component-section': true,
            'sw-icon': true,
            'sw-field-error': true,
        },
        mocks: {
            $tc: (...args) => JSON.stringify([...args]),
            $i18n: {
                locale: 'en-GB',
                messages: {
                    'en-GB': dictionary,
                },
            },
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve(responseMock),
                }),
            },
            stateStyleDataProviderService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
        computed: {
            systemCurrencyISOCode() {
                return 'EUR';
            },
            isSessionLoaded() {
                return true;
            },
        },
    };

    if (orderSumToday !== null) {
        options.computed.hasOrderToday = () => true;
        options.computed.orderSumToday = () => orderSumToday;
    }

    return shallowMount(await Shopware.Component.build('sw-dashboard-statistics'), options);
}

/**
 * @package merchant-services
 */
describe('module/sw-dashboard/component/sw-dashboard-statistics', () => {
    let wrapper = null;

    beforeAll(() => {
        if (Shopware.State.get('session')) {
            Shopware.State.unregisterModule('session');
        }

        Shopware.State.registerModule('session', {
            state: {
                currentUser: null,
            },
            mutations: {
                setCurrentUser(state, user) {
                    state.currentUser = user;
                },
            },
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

        expect(orderToday.exists()).toBeFalsy();
        expect(statisticsCount.exists()).toBeTruthy();
        expect(statisticsSum.exists()).toBeTruthy();
    });

    it('should not exceed decimal places of two', async () => {
        wrapper = await createWrapper(['order.viewer'], 43383.13234554);
        await flushPromises();

        const todaysTotalSum = wrapper.find('.sw-dashboard-statistics__intro-stats-today-single-stat:nth-of-type(2) span:nth-of-type(2)').text();
        expect(todaysTotalSum).toBe('€43,383.13');
    });

    it('should allow the possibility to extend the date ranges', async () => {
        Shopware.Component.override('sw-dashboard-statistics', {
            computed: {
                rangesValueMap() {
                    return [
                        ...this.$super('rangesValueMap'),
                        {
                            label: '72Hours',
                            range: 72,
                            interval: 'hour',
                        },
                        {
                            label: '90Days',
                            range: 90,
                            interval: 'day',
                        },
                    ];
                },
            },
        });

        wrapper = await createWrapper(['order.viewer']);
        await flushPromises();

        const dateRanges = wrapper.get('#sw-field--selectedRange').findAll('option');

        expect(dateRanges.at(dateRanges.length - 2).text()).toBe('["sw-dashboard.monthStats.dateRanges.72Hours"]');
        expect(dateRanges.at(dateRanges.length - 1).text()).toBe('["sw-dashboard.monthStats.dateRanges.90Days"]');
    });
});
