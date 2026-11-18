/**
 * @sw-package framework
 */

import { getTour, registerTour } from 'src/app/service/tour.service';

describe('src/app/service/tour.service', () => {
    it('returns the tours registered for a module', () => {
        const tours = {
            default: [{ selector: '.sw-jest', title: 'Jest' }],
        };

        registerTour('sw-jest-registered', tours);

        expect(getTour('sw-jest-registered')).toBe(tours);
    });

    it('returns undefined for a module without tours', () => {
        expect(getTour('sw-jest-unknown')).toBeUndefined();
    });

    it('replaces the tours of a module that registers again', () => {
        const replacement = { default: [{ selector: '.sw-jest-new' }] };

        registerTour('sw-jest-replaced', { default: [{ selector: '.sw-jest-old' }] });
        registerTour('sw-jest-replaced', replacement);

        expect(getTour('sw-jest-replaced')).toBe(replacement);
    });
});
