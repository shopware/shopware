import { mount } from '@vue/test-utils';
import dictionary from 'src/module/sw-dashboard/snippet/en-GB.json';

const hasOrderTodayMock = [
    {},
];

async function createWrapper(privileges = [], repository = {}) {
    const repositoryMock = {
        search: () => Promise.resolve([]),
        buildHeaders: () => {
        },
        ...repository,
    };

    return mount(await wrapTestComponent('sw-dashboard-statistics', { sync: true }), {
        global: {
            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-chart-card': await wrapTestComponent('sw-chart-card'),
                'sw-entity-listing': true,
                'sw-chart': true,
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
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
                    create: () => (repositoryMock),
                },
                stateStyleDataProviderService: {},
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
        },
    });
}

/**
 * @package services-settings
 */
describe('module/sw-dashboard/component/sw-dashboard-statistics', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Context.app.systemCurrencyISOCode = 'EUR';

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

        Shopware.Application.addInitializer('httpClient', () => {
            return {
                get: () => Promise.resolve({
                    data: {
                        statistic: [],
                    },
                }),
            };
        });
        jest.useFakeTimers('modern');
    });

    afterAll(() => {
        jest.useRealTimers();
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


    it('should show the todays stats', async () => {
        const orderSearchResult = {
            search: () => Promise.resolve([
                {
                    id: '1a2b3c',
                    orderNumber: '12345',
                    amountTotal: 123.45,
                    stateMachineState: {
                        name: 'open',
                    },
                },
                {
                    id: '1b2a3c',
                    orderNumber: '23456',
                    amountTotal: 19.45,
                    stateMachineState: {
                        name: 'closed',
                    },
                },
            ]),
        };

        orderSearchResult.criteris = { page: 1 };
        wrapper = await createWrapper(['order.viewer'], orderSearchResult);
        await flushPromises();

        const orderToday = wrapper.find('.sw-dashboard-statistics__intro-stats-today');

        expect(orderToday.exists()).toBeTruthy();
    });

    it('should call fetchTodayData and add stateMachineState association', async () => {
        const orderSearchResult = {
            search: jest.fn().mockResolvedValue([]),
        };

        wrapper = await createWrapper(['order.viewer'], orderSearchResult);
        await wrapper.vm.fetchTodayData();

        expect(orderSearchResult.search.mock.lastCall[0].associations[1].association).toBe('stateMachineState');
    });

    it('should not exceed decimal places of two', async () => {
        wrapper = await createWrapper(['order.viewer'], 43383.13234554);
        await wrapper.setData({
            hasOrderToday: hasOrderTodayMock,
            orderSumToday: 43383.13234554,
        });
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
