/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

export const apexchartStub = {
    name: 'apexchart',
    props: [
        'type',
        'options',
        'series',
        'height',
    ],
    template: '<div class="apexchart-stub"></div>',
};

// mock data
const chartOptions = {
    title: { text: 'Phenomenal chart' },
    chart: {
        toolbar: {
            show: true,
        },
    },
};

export const chartSeries = [
    {
        name: 'Demo Serie',
        data: [
            10,
            41,
            35,
            51,
            49,
            62,
            69,
            91,
            148,
        ],
    },
    {
        name: 'Another demo Serie',
        data: [
            12,
            24,
            35,
            58,
            88,
            95,
            125,
            145,
            148,
        ],
    },
];

export const setup = async ({ type, series, options, fillEmptyValues, sort, config } = {}) => {
    return mount(await wrapTestComponent('sw-chart', { sync: true }), {
        props: {
            type: type || 'line',
            series: series || chartSeries,
            options: options || chartOptions,
            fillEmptyValues: fillEmptyValues,
            sort: sort || false,
        },
        global: {
            stubs: { apexchart: apexchartStub },
            config,
        },
    });
};
