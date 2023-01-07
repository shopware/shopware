/**
 * @package admin
 */

/* eslint-disable no-console */
const fse = require('fs-extra');
const path = require('path');
const axios = require('axios');
const _get = require('lodash/get');

const APP_URL = process.env.APP_URL;
const PROJECT_ROOT = process.env.PROJECT_ROOT;
const DD_API_KEY = process.env.DD_API_KEY;


if (!APP_URL) {
    throw new Error('The environment variable "APP_URL" have to be defined.');
}

if (!PROJECT_ROOT) {
    throw new Error('The environment variable "PROJECT_ROOT" have to be defined.');
}

if (!DD_API_KEY) {
    // eslint-disable-next-line no-console
    console.warn('' +
        'WARNING: The environment variable "DD_API_KEY" have to defined. ' +
        'Otherwise it can\'t send metrics to datadog.');
}

async function iterateAsync(arr, handler) {
    await arr.reduce(async (promise, value) => {
        // This line will wait for the last async function to finish.
        // The first iteration uses an already resolved Promise
        // so, it will immediately continue.
        await promise;

        await handler(value);
    }, Promise.resolve());
}

function getTimeStamp() {
    return `${Math.floor(new Date().getTime() / 1000)}`;
}

function getScriptsSize(jsReport) {
    return jsReport.audits['network-requests'].details.items
        .filter((asset) => asset.resourceType === 'Script')
        .reduce((totalSize, asset) => {
            return totalSize + asset.resourceSize;
        }, 0);
}


async function sendMetrics(metrics) {
    console.log('SEND METRICS');

    const METRIC_SCORE_MAP = {
        // General scores
        performance: 'categories.performance.score',
        accessibility: 'categories.accessibility.score',
        seo: 'categories.seo.score',
        best_practices: 'categories.["best-practices"].score',
        pwa: 'categories.pwa.score',
        // Performance breakdown
        first_contentful_paint: 'audits["first-contentful-paint"].numericValue',
        speed_index: 'audits["speed-index"].numericValue',
        largest_contentful_paint: 'audits["largest-contentful-paint"].numericValue',
        time_to_interactive: 'audits["interactive"].numericValue',
        total_blocking_time: 'audits["total-blocking-time"].numericValue',
        cumulative_layout_shift: 'audits["cumulative-layout-shift"].numericValue',
        server_response_time: 'audits["server-response-time"].numericValue',
    };
    const timeStamp = getTimeStamp();

    const series = metrics.reduce((acc, metric) => {
        acc.push(...Object.entries(METRIC_SCORE_MAP).map(([metricName, scorePath]) => {
            return {
                host: 'lighthouse',
                type: 'gauge',
                metric: `lighthouse.${metricName}.${metric.testName}`,
                points: [[timeStamp, _get(metric.result, scorePath)]],
            };
        }));
        acc.push({
            host: 'lighthouse',
            type: 'gauge',
            metric: `lighthouse.total_bundle_size.${metric.testName}`,
            points: [[timeStamp, getScriptsSize(metric.result)]],
        });

        return acc;
    }, []);

    if (!DD_API_KEY) return undefined;

    return axios({
        method: 'post',
        url: 'https://api.datadoghq.eu/api/v1/series',
        headers: {
            'Content-Type': 'application/json',
            'DD-API-KEY': DD_API_KEY,
        },
        data: {
            series,
        },
    });
}

async function main() {
    const lighthouseTests = [];

    const urlMapFile = await fse.readFile(
        path.join(
            PROJECT_ROOT,
            '/build/artifacts/lighthouse-admin-config/urlmap.json',
        ),
        'utf-8',
    );
    const URL_MAP = JSON.parse(urlMapFile);

    const files = await fse.readdir('./.lighthouseci');

    await iterateAsync(files, async (file) => {
        if (path.extname(file) === '.json' && file.startsWith('lhr-')) {
            console.log(file);

            const jsonFile = await fse.readFile(path.join('./.lighthouseci', file), 'utf-8');
            const json = JSON.parse(jsonFile);

            const urlLabel = Object.keys(URL_MAP).find(key => URL_MAP[key] === json.requestedUrl)?.replace('_', '-');

            console.log('urlLabel:', urlLabel);

            const testNamePresent = Object.keys(lighthouseTests).find(key => lighthouseTests[key].testName === urlLabel);

            if (testNamePresent) {
                console.log(testNamePresent);
                return;
            }

            lighthouseTests.push({
                testName: urlLabel,
                result: json,
            });
        }
    });

    // Send results to dataDog
    await sendMetrics(lighthouseTests);
}

main();
