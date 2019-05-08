// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const path = require('path');

module.exports = {

    // The directory where Jest should store its cached dependency information
    // cacheDirectory: "/private/var/folders/00/scx5qv4s0fzdh47cvtj6xrj80000gn/T/jest_dx",

    // Automatically clear mock calls and instances between every test
    clearMocks: true,

    // The directory where Jest should output its coverage files
    coverageDirectory: 'coverage',

    // Automatically reset mock state between every test
    resetMocks: true,

    // Automatically restore mock state between every test
    restoreMocks: true,

    // The root directory that Jest should scan for tests and modules within
    rootDir: path.resolve(__dirname),

    // This option allows the use of a custom resolver.
    moduleNameMapper: {
        '^src/(.*)$': '<rootDir>/src/$1'
    },

    // The glob patterns Jest uses to detect test files
    testMatch: [
        '**/tests/**/*.test.js',
        '**/tests/*.test.js',
    ],
};
