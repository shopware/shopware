---
title: App requirements validation
date: 2025-09-05
area: core
tags: [app-system, validation, requirements]
---

## Context

The Shopware app system allows third-party applications to extend platform functionality. However, certain apps may require specific environment conditions or infrastructure capabilities to function properly. Without proper validation of these requirements, apps might fail at runtime or behave unpredictably, leading to poor user experience and difficult troubleshooting.

Examples of such requirements include:
- Public accessibility for webhook endpoints
- Secure HTTPS connections for data exchange
- Network connectivity for external service integrations
- Specific server configurations or capabilities

Currently, the app system lacks a standardized way to define and validate these requirements before app installation or updates, which can lead to:
- Silent failures in production environments
- Difficult debugging when apps don't work as expected
- Poor developer experience when requirements are not clearly communicated
- Security vulnerabilities when apps are installed in inappropriate environments

## Decision

We will implement a best-effort app requirements validation system that allows apps to declare their requirements in the manifest and validates these requirements during installation and update processes.

### Key Components

**1. Manifest Requirements Declaration**
Apps can declare requirements using a new `<requirements>` element in their manifest XML by adding empty child elements. Presence means the requirement is enabled:

```xml
<requirements>
    <public-access/>
    <!-- add further requirements as empty elements -->
    <!-- <another-requirement/> -->
    
</requirements>
```

**2. Requirement Interface**
All requirement validators implement a common `Requirement` interface with methods:
- `validate(Manifest $manifest): ?UnmetRequirement` - Validates the requirement and returns an `UnmetRequirement` on failure, or `null` on success
- `required(Manifest $manifest): bool` - Checks if this requirement applies to the app
- `name(): string` - Returns the requirement identifier

**3. Validator Architecture**
- `AppRequirementsValidator` orchestrates validation across all registered requirement validators
- Individual requirement classes (e.g., `PublicAccess`) handle specific validation logic
- Dependency injection allows easy extension with new requirement types

**4. Integration Points**
Requirements validation is integrated into:
- App installation process (`AppLifecycle::install()`)
- App update process (`AppLifecycle::update()`)
- Clear error reporting through `AppException::requirementsNotMet()`

**5. Initial Implementation**
The first requirement validator `PublicAccess` validates that:
- The `APP_URL` environment variable is configured
- The URL uses HTTPS scheme
- The URL is not a localhost or IP address
- The Shopware health check endpoint is publicly accessible

### Error Handling

When requirements are not met, a descriptive `AppException` is thrown with:
- Specific error code: `FRAMEWORK__APP_REQUIREMENTS_NOT_MET`
- Detailed violation descriptions including app name, requirement name, and actionable resolution
- HTTP 400 Bad Request status to indicate client-side configuration issue

### Extensibility

The system is designed for easy extension:
- New requirement validators can be added by implementing the `Requirement` interface
- Service container tag `app.requirements_validator` auto-registers new validators
- Abstract base class `AbstractRequirement` provides common functionality

## Consequences

**Positive Consequences:**
- **Improved Reliability**: Apps will fail fast before attempting to register with app backends, with clear error messages rather than failing silently at runtime
- **Better Developer Experience**: Clear requirement declarations and actionable error messages help developers understand environment needs
- **Enhanced Security**: Validation prevents apps from being installed in inappropriate environments (e.g., apps requiring public access being installed on localhost)
- **Easier Troubleshooting**: Standardized requirement validation provides consistent error reporting

**Potential Challenges:**
- **Additional Complexity**: Developers must understand and declare their app requirements
- **Validation Overhead**: Network checks (like public accessibility) add latency to installation process
- **False Positives**: Overly strict validation might prevent legitimate installations in edge cases
- **Backward Compatibility**: Existing apps without requirement declarations continue to work unchanged
- **Custom Requirements**: Shopware update is required to add new requirement types.

**Migration Strategy:**
- The feature is opt-in through manifest declarations
- Existing apps continue to function without modification
- New apps can gradually adopt requirement declarations as needed
- The `PublicAccess` validator includes caching to minimize performance impact

This implementation establishes a foundation for reliable app deployment while maintaining backward compatibility and providing clear guidance for both app developers and platform administrators.
