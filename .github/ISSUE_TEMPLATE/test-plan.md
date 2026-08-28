## Summary

Briefly describe what is being tested and why this test plan exists.

## Related artifacts

- Product concept:
- Design concept:
- Technical concept:
- Discussion:
- Related issues:

## Scope

Which areas are in scope for this test plan?

- [ ] Backend
- [ ] Administration
- [ ] Storefront
- [ ] Store API
- [ ] Admin API
- [ ] CLI / scheduled task
- [ ] Elasticsearch / indexing
- [ ] Caching
- [ ] Permissions / ACL
- [ ] Performance
- [ ] Cloud / deployment concerns

## Out of scope

What is explicitly not covered by this test plan?

- TBD

## Acceptance criteria covered

Reference the parent issue's acceptance criteria that are covered by this test plan.

- [ ] TBD

## Preconditions / setup

Describe everything required before testing starts.

### Environment

- Environment:
- Version / branch:
- Feature flag(s):
- Required data:
- Required permissions / role:
- License / extension requirements:
- Browser / device requirements:
- API client / credentials:

### Preparation steps

- [ ] TBD

## Test scenario overview

Create and link dedicated sub-issues for manual and/or automated test cases where needed.

When creating dedicated scenario sub-issues from this test plan, create a blank sub-issue and copy the structure from `.github/ISSUE_TEMPLATE/test-scenario.md` into the issue body.

Suggested scenario groups:
- Happy path
- Negative / validation scenarios
- Edge cases
- Regression scenarios

## Area-specific checks

### Administration
- [ ] UI works as expected
- [ ] Validation messages are correct
- [ ] ACL restrictions are enforced
- [ ] Listing / detail / editing flows work

### Storefront
- [ ] Feature is visible only where expected
- [ ] Behaviour works across supported browsers/devices
- [ ] No obvious layout or usability regressions

### API
- [ ] Endpoints behave as expected
- [ ] Request validation works
- [ ] Response payload is correct
- [ ] Error cases are covered

### Data / persistence
- [ ] Data is stored correctly
- [ ] Updates are persisted correctly
- [ ] Deletion / cleanup works
- [ ] Indexing / cache refresh behavior is validated

### Permissions / ACL
- [ ] Authorized users can access the feature
- [ ] Unauthorized users are blocked
- [ ] Permission changes are respected

### Performance / reliability
- [ ] No obvious performance degradation
- [ ] No unnecessary reload / indexing / expensive operations observed
- [ ] Relevant background processes behave correctly

## Risks, open questions, and follow-up issues

### Risks
- TBD

### Open questions
- TBD

### Follow-up issues
- TBD
