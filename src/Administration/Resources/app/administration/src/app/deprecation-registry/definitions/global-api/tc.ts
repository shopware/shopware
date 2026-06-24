import { globalApiMigration, reference, renameCall } from '../helpers';

export default globalApiMigration({
    id: 'global.$tc',
    api: '$tc',
    deprecatedIn: '6.8.0',
    removedIn: '6.9.0',
    description: 'The $tc translation helper is deprecated. Use $t and pass count interpolation explicitly.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.8.md#removal-of-tc-function' }),
    ],
    usage: [
        renameCall({
            from: '$tc',
            to: '$t',
            fix: 'unsafe-auto',
            message: 'After the fixer replaces the call, verify pluralization and count interpolation manually.',
        }),
    ],
});
