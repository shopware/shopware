/**
 * @private
 * @package admin
 */

import { checkDalAssociationPrivileges } from './check-dal-association-privileges';

describe('scripts/acl/check-dal-association-privileges', () => {
    it('finds no missing privileges for statically resolvable Administration DAL associations', () => {
        expect(checkDalAssociationPrivileges()).toEqual([]);
    });
});
