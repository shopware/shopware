/**
 * @package admin
 */

import eventBus from 'src/core/service/utils/eventBus.utils';

describe('src/core/service/utils/eventBus.utils', () => {
    it('should return a event bus', () => {
        expect(eventBus).toHaveProperty('on');
        expect(eventBus).toHaveProperty('off');
        expect(eventBus).toHaveProperty('emit');
    });
});
