/**
 * @package admin
 */

import vueHelper from 'src/core/service/utils/vue-helper.utils';

describe('src/core/service/utils/vue-helper.utils', () => {
    it('should contain method "getCompatChildren"', () => {
        expect(vueHelper.getCompatChildren).toBeDefined();
    });
});
