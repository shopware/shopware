/**
 * @package admin
 */

import 'src/index';

jest.mock('src/core/shopware', () => jest.fn());
jest.mock('src/app/main', () => jest.fn());

describe('src/index', () => {
    // eslint-disable-next-line jest/expect-expect
    it('should import the core and the app', () => {
        /**
         * Direct imports can't be tested. Only exported mocked values can be checked
         */
    });
});
