/**
 * Carries an intentional HTTP sequence blocker through helper methods without losing evidence.
 *
 * The sequence runner catches this error type and converts it back into evaluation data; other
 * exceptions still surface as real executor failures.
 */
export class HttpRequestBlocked extends Error {
  reason: string;

  constructor(reason: string) {
    super(reason);
    this.reason = reason;
  }
}
