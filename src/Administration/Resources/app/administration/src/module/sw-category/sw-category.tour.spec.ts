/**
 * @sw-package discovery
 */

import { getTour } from 'src/app/service/tour.service';
import 'src/module/sw-category/sw-category.tour';

describe('src/module/sw-category/sw-category.tour', () => {
    it('registers the default and the detail tour for the category module', () => {
        const tours = getTour('sw-category');

        expect(Object.keys(tours ?? {})).toEqual([
            'default',
            'detail',
        ]);
    });

    it.each([
        'default',
        'detail',
    ])('gives every step of the %s tour a target, a title and a text', (key) => {
        const steps = getTour('sw-category')?.[key] ?? [];

        expect(steps.length).toBeGreaterThan(0);
        steps.forEach((step) => {
            expect(step.selector).toBeTruthy();
            expect(step.title).toBeTruthy();
            expect(step.text).toBeTruthy();
        });
    });
});
