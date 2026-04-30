/**
 * @sw-package framework
 */

import { getCiGuardVariables } from './environment';

describe('admin-plugin-compatibility CI guard', () => {
    it.each([
        'CI',
        'GITHUB_ACTIONS',
        'GITLAB_CI',
    ])('fails when %s is set', (name) => {
        expect(getCiGuardVariables({ [name]: 'false' })).toEqual([name]);
    });

    it('passes when no CI guard variables are set', () => {
        expect(getCiGuardVariables({ NODE_ENV: 'test' })).toEqual([]);
    });
});
