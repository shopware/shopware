/**
 * @sw-package discovery
 */

import { shallowMount } from '@vue/test-utils';
import swSalesChannelDetailProductExportInsights from './index';

Shopware.Component.register('sw-sales-channel-detail-product-export-insights', swSalesChannelDetailProductExportInsights);

const testDates = {
    dec30th12pm: '2023-12-30T12:00:00+00:00',
    dec31st1pm: '2023-12-31T13:00:00+00:00',
    dec31st1pm10m: '2023-12-31T13:10:00+00:00',
    dec31st2pm20m: '2023-12-31T14:20:00+00:00',
};

const userTimezoneMilliseconds = {
    dec30th: Shopware.Utils.format.dateWithUserTimezone(new Date('2023-12-30T00:00:00+00:00')).getTime(),
    dec31st: Shopware.Utils.format.dateWithUserTimezone(new Date('2023-12-31T00:00:00+00:00')).getTime(),
    dec30th12pm: Shopware.Utils.format.dateWithUserTimezone(new Date('2023-12-30T12:00:00+00:00')).getTime(),
    dec31st1pm: Shopware.Utils.format.dateWithUserTimezone(new Date('2023-12-31T13:00:00+00:00')).getTime(),
    dec31st2pm: Shopware.Utils.format.dateWithUserTimezone(new Date('2023-12-31T14:00:00+00:00')).getTime(),
};

async function createWrapper(aclPermissions = {}) {
    const defaultPermissions = {
        'sales_channel.viewer': true,
    };

    const mergedPermissions = { ...defaultPermissions, ...aclPermissions };

    const orderTrackingRepositoryMock = {
        search: jest.fn().mockResolvedValue([
            {
                id: 'tracking-order-1',
                createdAt: testDates.dec30th12pm,
                order: {
                    id: 'order1',
                    orderDateTime: testDates.dec30th12pm,
                    amountTotal: 100,
                },
            },
            {
                id: 'tracking-order-2',
                createdAt: testDates.dec31st1pm,
                order: {
                    id: 'order2',
                    orderDateTime: testDates.dec31st1pm,
                    amountTotal: 200,
                },
            },
            {
                id: 'tracking-order-3',
                createdAt: testDates.dec31st1pm10m,
                order: {
                    id: 'order3',
                    orderDateTime: testDates.dec31st1pm10m,
                    amountTotal: 300,
                },
            },
            {
                id: 'tracking-order-4',
                createdAt: testDates.dec31st2pm20m,
                order: {
                    id: 'order4',
                    orderDateTime: testDates.dec31st2pm20m,
                    amountTotal: 100,
                },
            },
        ]),
    };

    const customerTrackingRepositoryMock = {
        search: jest.fn().mockResolvedValue([
            {
                id: 'tracking-customer-1',
                createdAt: testDates.dec30th12pm,
                customer: {
                    id: 'customer1',
                    createdAt: testDates.dec30th12pm,
                },
            },
            {
                id: 'tracking-customer-2',
                createdAt: testDates.dec31st1pm,
                customer: {
                    id: 'customer2',
                    createdAt: testDates.dec31st1pm,
                },
            },
            {
                id: 'tracking-customer-3',
                createdAt: testDates.dec31st1pm10m,
                customer: {
                    id: 'customer3',
                    createdAt: testDates.dec31st1pm10m,
                },
            },
            {
                id: 'tracking-customer-4',
                createdAt: testDates.dec31st2pm20m,
                customer: {
                    id: 'customer4',
                    createdAt: testDates.dec31st2pm20m,
                },
            },
        ]),
    };

    const mockRepositoryFactory = {
        create: (entity) => {
            if (entity === 'sales_channel_tracking_order') {
                return orderTrackingRepositoryMock;
            }

            if (entity === 'sales_channel_tracking_customer') {
                return customerTrackingRepositoryMock;
            }

            return { search: jest.fn().mockResolvedValue([]) };
        },
    };

    return shallowMount(await Shopware.Component.build('sw-sales-channel-detail-product-export-insights'), {
        props: {
            salesChannel: {
                id: 'product-export-sales-channel-id',
                typeId: Shopware.Defaults.productComparisonTypeId,
            },
        },
        global: {
            provide: {
                repositoryFactory: mockRepositoryFactory,
                acl: {
                    can: (permission) => mergedPermissions[permission] !== false,
                },
            },
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot /></div>',
                },
                'sw-chart-card': {
                    template: '<div class="sw-chart-card" />',
                    props: [
                        'cardSubtitle',
                        'series',
                        'options',
                        'availableRanges',
                        'defaultRangeIndex',
                        'fillEmptyValues',
                        'type',
                        'positionIdentifier',
                        'sort',
                    ],
                },
            },
        },
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-product-export-insights', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }

        jest.clearAllMocks();
    });

    it('is a Vue component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('renders all insight cards when user can view orders and customers', () => {
        const cards = wrapper.findAll('.sw-chart-card');

        expect(cards).toHaveLength(3);
    });

    it('renders no insight cards when sales channel viewer permission is missing', async () => {
        const noViewerWrapper = await createWrapper({ 'sales_channel.viewer': false });

        expect(noViewerWrapper.findAll('.sw-chart-card')).toHaveLength(0);

        noViewerWrapper.unmount();
    });

    it('includes 180 days in available date ranges', () => {
        expect(wrapper.vm.availableRanges).toContain('180Days');
        expect(wrapper.vm.statisticDateRangesOrderCount.options).toHaveProperty('180Days', 180);
        expect(wrapper.vm.statisticDateRangesCustomerCount.options).toHaveProperty('180Days', 180);
        expect(wrapper.vm.statisticDateRangesOrderSum.options).toHaveProperty('180Days', 180);
    });

    it('defaults all charts to last 7 days', () => {
        expect(wrapper.vm.statisticDateRangesOrderCount.value).toBe('7Days');
        expect(wrapper.vm.statisticDateRangesCustomerCount.value).toBe('7Days');
        expect(wrapper.vm.statisticDateRangesOrderSum.value).toBe('7Days');
        expect(wrapper.vm.defaultRangeIndex).toBe(wrapper.vm.availableRanges.indexOf('7Days'));
    });

    it('calculates summary values for all cards', () => {
        const originalGetByName = Shopware.Filter.getByName;

        Shopware.Filter.getByName = jest.fn().mockImplementation((name) => {
            if (name === 'currency') {
                return (value) => `formatted-${value}`;
            }

            return originalGetByName(name);
        });

        wrapper.vm.historyOrderDataCount = [
            { id: '1' },
            { id: '2' },
            { id: '3' },
        ];
        wrapper.vm.historyCustomerDataCount = [
            { id: '1' },
            { id: '2' },
        ];
        wrapper.vm.historyOrderDataSum = [
            { amountTotal: 100 },
            { amountTotal: 250 },
        ];

        expect(wrapper.vm.orderCountSummary).toBe(3);
        expect(wrapper.vm.customerCountSummary).toBe(2);
        expect(wrapper.vm.orderSumSummary).toBe('formatted-350');

        Shopware.Filter.getByName = originalGetByName;
    });

    it('contains sales channel tracking filters in order and customer criteria', () => {
        const orderCriteria = wrapper.vm.orderCountCriteria.parse();
        const customerCriteria = wrapper.vm.customerCountCriteria.parse();

        expect(orderCriteria.filter).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    field: 'salesChannelId',
                    value: 'product-export-sales-channel-id',
                }),
                expect.objectContaining({
                    field: 'createdAt',
                }),
            ]),
        );

        expect(customerCriteria.filter).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    field: 'salesChannelId',
                    value: 'product-export-sales-channel-id',
                }),
                expect.objectContaining({
                    field: 'createdAt',
                }),
            ]),
        );
    });

    it('contains paid transaction filter in turnover criteria', () => {
        const turnoverCriteria = wrapper.vm.orderSumCriteria.parse();

        expect(turnoverCriteria.associations).toHaveProperty('order');
        expect(turnoverCriteria.filter).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    field: 'salesChannelId',
                    value: 'product-export-sales-channel-id',
                }),
                expect.objectContaining({
                    field: 'order.transactions.stateMachineState.technicalName',
                    value: 'paid',
                }),
                expect.objectContaining({
                    field: 'createdAt',
                }),
            ]),
        );
    });

    it('fetchData skips all requests when sales channel viewer permission is missing', async () => {
        const noViewerWrapper = await createWrapper({ 'sales_channel.viewer': false });

        noViewerWrapper.vm.orderTrackingRepository.search.mockClear();
        noViewerWrapper.vm.customerTrackingRepository.search.mockClear();

        await noViewerWrapper.vm.fetchData();

        expect(noViewerWrapper.vm.orderTrackingRepository.search).not.toHaveBeenCalled();
        expect(noViewerWrapper.vm.customerTrackingRepository.search).not.toHaveBeenCalled();

        noViewerWrapper.unmount();
    });

    it('updates date ranges and refetches data on range updates', async () => {
        const getOrderCountSpy = jest.spyOn(wrapper.vm, 'getHistoryOrderCountData');
        const getOrderSumSpy = jest.spyOn(wrapper.vm, 'getHistoryOrderSumData');
        const getCustomerCountSpy = jest.spyOn(wrapper.vm, 'getHistoryCustomerCountData');

        await wrapper.vm.onOrderCountRangeUpdate('14Days');
        await wrapper.vm.onOrderSumRangeUpdate('7Days');
        await wrapper.vm.onCustomerCountRangeUpdate('yesterday');

        expect(getOrderCountSpy).toHaveBeenCalledTimes(1);
        expect(getOrderSumSpy).toHaveBeenCalledTimes(1);
        expect(getCustomerCountSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.statisticDateRangesOrderCount.value).toBe('14Days');
        expect(wrapper.vm.statisticDateRangesOrderSum.value).toBe('7Days');
        expect(wrapper.vm.statisticDateRangesCustomerCount.value).toBe('yesterday');
    });

    it('builds daily order, customer and turnover series', () => {
        expect(wrapper.vm.orderCountSeries[0].data).toEqual(
            expect.arrayContaining([
                { x: userTimezoneMilliseconds.dec30th, y: 1 },
                { x: userTimezoneMilliseconds.dec31st, y: 3 },
            ]),
        );

        expect(wrapper.vm.customerCountSeries[0].data).toEqual(
            expect.arrayContaining([
                { x: userTimezoneMilliseconds.dec30th, y: 1 },
                { x: userTimezoneMilliseconds.dec31st, y: 3 },
            ]),
        );

        expect(wrapper.vm.orderSumSeries[0].data).toEqual(
            expect.arrayContaining([
                { x: userTimezoneMilliseconds.dec30th, y: 100 },
                { x: userTimezoneMilliseconds.dec31st, y: 600 },
            ]),
        );
    });

    it('builds hourly order series for 24 hours range', async () => {
        await wrapper.vm.onOrderCountRangeUpdate('24Hours');

        const series = wrapper.vm.orderCountSeries;

        expect(wrapper.vm.getTimeUnitInterval(wrapper.vm.statisticDateRangesOrderCount)).toBe('hour');
        expect(series[0].data).toEqual(
            expect.arrayContaining([
                { x: userTimezoneMilliseconds.dec30th12pm, y: 1 },
                { x: userTimezoneMilliseconds.dec31st1pm, y: 2 },
                { x: userTimezoneMilliseconds.dec31st2pm, y: 1 },
            ]),
        );
    });

    it('uses currency formatter for turnover chart options', () => {
        const originalGetByName = Shopware.Filter.getByName;

        Shopware.Filter.getByName = jest.fn().mockImplementation((name) => {
            if (name === 'currency') {
                return () => 'formatted-currency';
            }

            return originalGetByName(name);
        });

        const chartOptions = wrapper.vm.chartOptionsOrderSum;

        expect(chartOptions.yaxis.labels.formatter(10.5)).toBe('formatted-currency');

        Shopware.Filter.getByName = originalGetByName;
    });

    it('localizes tooltip date formatting based on the selected range', () => {
        const dateTimeFormatSpy = jest.spyOn(Intl, 'DateTimeFormat').mockImplementation(() => {
            return {
                format: () => 'localized-date',
            };
        });

        const defaultRangeResult = wrapper.vm.formatTooltipDate(
            userTimezoneMilliseconds.dec31st1pm,
            wrapper.vm.statisticDateRangesOrderCount,
        );

        expect(defaultRangeResult).toBe('localized-date');
        expect(dateTimeFormatSpy).toHaveBeenLastCalledWith(
            expect.any(String),
            expect.objectContaining({
                day: '2-digit',
                month: 'short',
            }),
        );

        wrapper.vm.statisticDateRangesOrderCount.value = '24Hours';

        const hourlyRangeResult = wrapper.vm.formatTooltipDate(
            userTimezoneMilliseconds.dec31st1pm,
            wrapper.vm.statisticDateRangesOrderCount,
        );

        expect(hourlyRangeResult).toBe('localized-date');
        expect(dateTimeFormatSpy).toHaveBeenLastCalledWith(
            expect.any(String),
            expect.objectContaining({
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            }),
        );
        expect(wrapper.vm.formatTooltipDate('not-a-timestamp', wrapper.vm.statisticDateRangesOrderCount)).toBe('');

        dateTimeFormatSpy.mockRestore();
    });

    it('handles invalid and null datetime values for aggregation', () => {
        expect(wrapper.vm.formatDateStringToAggregationTime(null)).toBeNull();
        expect(wrapper.vm.formatDateStringToAggregationTime('not-a-date')).toBeNull();
        expect(wrapper.vm.formatDateStringToAggregationTime('2024-12-24T18:32:14.451+00:00')).not.toBeNull();
        expect(wrapper.vm.formatDateStringToAggregationTime('2024-12-24T18:32:14.451+00:00', true)).not.toBeNull();
    });
});
