# Apps (Skeleton)

- Definition: External (no direct script injection) integrations rendered via iframe with secure bridge
- Use cases: Extensions which don't need to change internal code behavior
- Technical model:
  - iFrame injection with origin isolation
  - postMessage communication channel
  - Admin Extension SDK (Meteor SDK) providing API surface
- Capabilities (placeholder list): navigation, action buttons, entity actions, custom modules, custom UI, ...
- Security considerations:
  - CSP & sandbox attributes
  - Permission scoping vs plugin full access
- Performance aspects:
  - Lazy-load iframe only when route visited
  - (De-)serialization of messages
  - Multiple iFrame instances on the same page creates multiple browser processes
- Limitations vs plugins:
  - No direct component modifications
  - Latency of cross-frame messaging
