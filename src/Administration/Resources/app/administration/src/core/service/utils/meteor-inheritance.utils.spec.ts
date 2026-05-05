/**
 * @sw-package framework
 */

import { mapInheritanceSlotPropsToMeteorProps } from './meteor-inheritance.utils';

describe('src/core/service/utils/meteor-inheritance.utils', () => {
    it('builds meteor inheritance config from sw-inherit-wrapper slot props', () => {
        const inheritance = {
            isInheritField: true,
            isInherited: true,
            removeInheritance: jest.fn(),
            restoreInheritance: jest.fn(),
        };

        expect(mapInheritanceSlotPropsToMeteorProps(inheritance, 'parent value')).toEqual({
            isInheritanceField: true,
            isInherited: true,
            inheritanceRemove: inheritance.removeInheritance,
            inheritanceRestore: inheritance.restoreInheritance,
            inheritedValue: 'parent value',
        });
    });

    it('does not expose inherited value when the field has no inheritance parent', () => {
        const inheritance = {
            isInheritField: false,
            isInherited: false,
            removeInheritance: jest.fn(),
            restoreInheritance: jest.fn(),
        };

        expect(mapInheritanceSlotPropsToMeteorProps(inheritance, 'parent value')).toEqual({
            isInheritanceField: false,
            isInherited: false,
            inheritanceRemove: inheritance.removeInheritance,
            inheritanceRestore: inheritance.restoreInheritance,
            inheritedValue: null,
        });
    });

    it('returns an empty config without inheritance props', () => {
        expect(mapInheritanceSlotPropsToMeteorProps()).toEqual({});
    });
});
