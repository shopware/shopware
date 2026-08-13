/**
 * The outcome of one component migration, shared by the script transformer and
 * the SFC merger: a script that cannot be converted has no SFC to merge, so both
 * layers report the same three states.
 */
export type MigrationStatus = 'fully-migrated' | 'partially-migrated' | 'not-migratable';

/** Reported instead of a component name when the registration uses a non-literal name. */
export const UNKNOWN_COMPONENT_NAME = 'unknown-component';
