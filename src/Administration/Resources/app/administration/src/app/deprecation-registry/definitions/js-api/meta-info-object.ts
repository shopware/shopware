import { jsApiMigration, reference, replaceObjectOption } from '../helpers';

export default jsApiMigration({
    id: 'js-api.meta-info-object',
    deprecatedIn: '6.6.0',
    removedIn: '6.7.0',
    description:
        'Providing metaInfo as an object is not supported anymore with the vue-meta removal. Return meta information from a metaInfo function instead.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.6.md#removal-of-vue-meta' }),
    ],
    usage: [
        replaceObjectOption({ from: 'metaInfo', to: 'metaInfo() { return {}; }' }),
    ],
});
