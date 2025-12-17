const { resolve, relative } = require('path');

const lighthouseAuthPath = relative(process.cwd(), resolve(__dirname, 'lighthouse-auth.js'));

/**
 * Lighthouse CI Configuration
 *
 * This configuration is used to measure real-world performance of the Administration panel.
 * It focuses on boot process and interactivity metrics.
 *
 * @see https://github.com/GoogleChrome/lighthouse-ci
 * @sw-package framework
 */

module.exports = {
    ci: {
        collect: {
            // Measure the dashboard after login (authenticated performance)
            url: [
                'http://localhost:8000/admin#/sw/dashboard/index',
                'http://localhost:8000/admin#/sw/product/index',
            ],
            numberOfRuns: 3,
            // Use puppeteer script to authenticate before running Lighthouse
            puppeteerScript: lighthouseAuthPath,
            puppeteerLaunchOptions: {
                args: ['--no-sandbox', '--disable-setuid-sandbox'],
                userDataDir: './.lighthouseci/admin-profile',
            },
            settings: {
                // Use desktop preset since admin is primarily used on desktop
                preset: 'desktop',
                // Disable CPU throttling for more consistent CI results
                throttling: {
                    cpuSlowdownMultiplier: 1,
                },
                // Skip audits that are not relevant for an admin panel or localhost testing
                skipAudits: [
                    'is-crawlable',
                    'robots-txt',
                    'canonical',
                    'structured-data',
                    // Skip HTTPS audit since we're testing on localhost
                    'is-on-https',
                    'redirects-http',
                ],
            },
        },
        assert: {
            assertions: {
                // Overall performance score must be at least 40%
                'categories:performance': ['error', { minScore: 0.4 }],

                // First Contentful Paint: User should see something within 6 seconds
                'first-contentful-paint': ['error', { maxNumericValue: 6000 }],

                // Time to Interactive: Admin must be usable within 10 seconds
                'interactive': ['error', { maxNumericValue: 10000 }],

                // Total Blocking Time: Prevent excessive JS blocking (600ms threshold)
                'total-blocking-time': ['error', { maxNumericValue: 600 }],

                // Largest Contentful Paint: Main UI should render within 8 seconds
                'largest-contentful-paint': ['error', { maxNumericValue: 8000 }],

                // Speed Index: Content should be visually populated reasonably fast
                'speed-index': ['warn', { maxNumericValue: 6000 }],
            },
        },
        upload: {
            // Upload reports to temporary public storage for PR comments
            target: 'temporary-public-storage',
        },
    },
};

