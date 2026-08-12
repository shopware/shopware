import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import { buildSlackPayload } from './notify-qops-success-manager-slack.ts';

describe('buildSlackPayload', () => {
  it('appends the run URL to the agent-provided text', () => {
    const payload = buildSlackPayload(
      { text: 'Platform trunk: green. PaaS: NEW failure, first observed last night.' },
      'https://github.com/shopware/shopware/actions/runs/123',
    );

    assert.equal(
      payload.text,
      'Platform trunk: green. PaaS: NEW failure, first observed last night.\nSee https://github.com/shopware/shopware/actions/runs/123',
    );
  });

  it('falls back to a visible notice when the summary has no text field', () => {
    const payload = buildSlackPayload({}, 'https://github.com/shopware/shopware/actions/runs/123');

    assert.match(payload.text, /did not produce a summary/);
    assert.match(payload.text, /https:\/\/github\.com\/shopware\/shopware\/actions\/runs\/123/);
  });

  it('falls back when the text field is present but blank', () => {
    const payload = buildSlackPayload({ text: '   ' }, 'https://example.test/run');

    assert.match(payload.text, /did not produce a summary/);
  });
});
