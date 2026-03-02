 Consent Module

 Status
 - Experimental: This module is currently experimental. Its APIs, behavior, and storage schema may change without backward compatibility guarantees. This is for internal use only.

 Overview
 - The consent module enables defining, managing, and querying consents in Shopware. Managing means providing functionality to accept and revoke consents based on the scope of the consent
 - This module *does not* use the consent state to perform any other actions; it purely serves as a system to manage the state.

 Key concepts
 - Consent Definition: A consent is a class-based definition representing something which requires consent. It is an implementation of the `ConsentDefinition` interface and consists of a unique name, a scope, and an available since date.
 - Permissions: A Consent Definition can be associated with permissions, which are required to accept or revoke the consent. If the current user does not have the required permissions, they cannot perform consent actions.
 - Scope: The scope of a consent describes who or what can perform consent actions, eg. accepting or revoking.
 - State: Each consent has a `status` of `unset`, `accepted`, or `revoked` for a given `identifier` within its scope. When there is no state for a consent/scope id combination in the storage, the status is interpreted as `unset`.
 - Actor: The username of the Admin user who made the last change to a consent decision.

 Examples
 - Admin user scope
   - Consent: `dashboard_ai_insights` (Show insights from AI in dashboard)
   - admin-1 accepted; admin-2 revoked: different states per user.
   - Note: the `identifier` is always the userId of the user who gave the consent.

 - System scope
   - Consent: `error_reporting` (Upload all errors to Shopware)
   - Accepted by admin-1, later revoked by admin-2: one shared state per system.
   - Note: `identifier` is always `system`.

 ## Interfaces
 ### ConsentDefinition
 File: `src/Core/System/Consent/ConsentDefinition.php`
 ```php
 interface ConsentDefinition {
   public function getName(): string;               // Unique machine name of the consent (e.g. "backend_data")
   public function getScopeName(): string;          // Name of the scope (see ConsentScope implementations)
   public function getSince(): \DateTimeImmutable;  // Introduction date of the consent
   public function getRequiredPermissions(): array; // Array of permission strings required to accept/revoke this consent
 }
 ```

 ### ConsentScope
 File: `src/Core/System/Consent/ConsentScope.php`
 ```php
 interface ConsentScope {
   public function getName(): string;                                // Unique machine name scope name (e.g. "admin_user")
   public function resolveIdentifier(Context $context): string;      // How to resolve the context to the id that the scope represents
   public function resolveActorIdentifier(Context $context): string; // How to resolve the context to the id of the actor that performed the action
 }
 ```

When scope implementations cannot resolve to an ID from the context, they must throw `ConsentException::cannotResolveScope()`


 ## Bundled Scopes
 - AdminUser
   - File: `src/Core/System/Consent/ConsentScope/AdminUser.php`
   - Identifier: the current Admin API user id.
   - Actor: same as identifier.

 - System
   - File: `src/Core/System/Consent/ConsentScope/System.php`
   - Identifier: literal `system` (global scope).
   - Actor: the current Admin API user id who performed the action.

 ## Bundled consents
 - Backend data
   - File: `src/Core/System/Consent/Definition/BackendData.php`
   - Scope: `system`
   - Description: System-wide consent to collect or process backend-related data.

 - ProductAnalytics
   - File: `src/Core/System/Consent/Definition/ProductAnalytics.php`
   - Scope: `admin_user`
   - Description: Per-admin-user consent for Admin usage tracking.

## PHP API's

The main API of the module is the `ConsentService` class.
  - Methods:
   - `list(Context $context): array<ConsentState>`
     - Returns the state of all consents in the system for the given context. For example, when the context resolves to an admin user, this method will return the states for system scopes and only their states for admin user scopes. In other words, the current admin user will see only *their* state, not other admin users.
   - `getConsentState(string $name, Context $context): ConsentState`
     - Returns the `ConsentState` for a single consent and context combination.
   - `acceptConsent(string $name, Context $context): void`
     - Persists state as `ACCEPTED` for the given consent and context combination.
   - `revokeConsent(string $name, Context $context): void`
     - Persists state as `REVOKED` for the given consent and context combination.

## HTTP API

The HTTP API is essentially a mirror of `ConsentService`, exposing the same methods. See the API schema for more details.

Events
 - `ConsentAcceptedEvent`
   - Dispatched after persisting an acceptance. Carries: `name`, `scopeName`, `identifier`.

 - `ConsentRevokedEvent`
   - Dispatched after persisting a revocation. Carries: `name`, `scopeName`, `identifier`.

### Persistence
The consent state is stored in the database table: `consent_state`. When a consent is neither accepted nor revoked, the state is considered `requested`. This initial state is not stored in the database.

## Adding a new consent
 1) Implement a `ConsentDefinition` class
 2) Register it as a service and tag it with `shopware.consent.definition`.
 3) If you need a new scope, implement `ConsentScope`, register it as a service, and tag it with `shopware.consent.scope`. Ensure it resolves both the identifier and actor from the `Context` or throws an appropriate `ConsentException`.

