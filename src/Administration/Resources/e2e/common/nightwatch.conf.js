const process = require('process');
const chromedriver = require('chromedriver');

const chromeOptions = [
    '--lang=en_GB,en',
    '--window-size=1920,1080',
    '--remote-debugging-port=9222',
    '--disable-web-security',
    '--no-sandbox',
    '--ignore-certificate-errors'
];

if (process.env.NIGHTWATCH_HEADLESS === 'true') {
    chromeOptions.push('--headless');
}

module.exports = {
    output_folder: 'build/artifacts/e2e',

    webdriver: {
        start_process: true,
        server_path: chromedriver.path,
        port: 9515
    },

    test_settings: {
        default: {
            filter: '**/*.spec.js',

            screenshots: {
                enabled: true,
                on_failure: true,
                path: 'build/artifacts/e2e/screenshots/'
            },
            desiredCapabilities: {
                browserName: 'chrome',
                javascriptEnabled: true,
                acceptSslCerts: true,
                chromeOptions: {
                    prefs: {
                        'intl.accept_languages': 'en_GB,en'
                    },
                    args: chromeOptions
                }
            }
        },
        docker: {
            filter: '**/*.spec.js',

            screenshots: {
                enabled: true,
                on_failure: true,
                path: 'build/artifacts/e2e/screenshots/'
            },
            desiredCapabilities: {
                browserName: 'chrome',
                javascriptEnabled: true,
                acceptSslCerts: true,
                chromeOptions: {
                    prefs: {
                        'intl.accept_languages': 'en_GB,en'
                    },
                    args: [
                        '--lang=en_GB,en',
                        '--window-size=1920,1080',
                        '--remote-debugging-port=9222',
                        '--no-sandbox',
                        '--headless',
                        '--disable-web-security',
                        '--ignore-certificate-errors'
                    ]
                }
            }
        }
    }
};
