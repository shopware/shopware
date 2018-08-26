require('babel-register');

const process = require('process');

module.exports = {
    src_folders: ['vendor/shopware/platform/src/Administration/Resources/administration/test/e2e/specs'],
    output_folder: 'build/artifacts/e2e',

    selenium: {
        start_process: false,
        host: 'selenium',
        port: 4444
    },

    test_settings: {
        default: {
            filter: '**/*.spec.js',
            launch_url: `${process.env.APP_URL}/admin`,
            selenium_host: 'selenium',
            screenshots: {
                enabled: false,
                on_failure: true,
                path: 'build/artifacts/e2e/screenshots/'
            },
            desiredCapabilities: {
                browserName: 'chrome',
                javascriptEnabled: true,
                acceptSslCerts: true,
                chromeOptions: {
                    args: [
                        '--remote-debugging-port=9222',
                        '--no-sandbox',
                        '--headless',
                        '--disable-web-security',
                        '--ignore-certificate-errors'
                    ]
                }
            },
            globals: {
                waitForConditionTimeout: 5000
            }
        }
    }
};
