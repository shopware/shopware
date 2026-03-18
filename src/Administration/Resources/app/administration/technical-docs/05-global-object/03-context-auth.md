# Context & Authentication

The Shopware 6 Administration provides a sophisticated context and authentication system that manages user sessions, API access tokens, and application context information. This system ensures secure communication with the backend API while maintaining user session state across the application.

## Context Types

### API Context
The API context contains information about the current API state and configuration:

- **Language Context**: Current language ID and system language settings
- **Currency Context**: Currency information for price calculations
- **Version Context**: API version and live version IDs for data versioning
- **Environment**: Development, production, or testing environment
- **Paths**: API endpoints, resource paths, and installation paths

### User Session Context
The user session context manages authentication state and user-specific information:

- **Authentication Token**: Access and refresh tokens with expiry timestamps
- **User Activity**: Last activity tracking for automatic logout
- **Remember Me**: Persistent login preferences
- **Session Storage**: Temporary session data and route information

## Authentication Flow

### 1. Login Request
The authentication process begins with a username/password login request:

```javascript
// Login endpoint: /oauth/token
const loginData = {
    grant_type: 'password',
    client_id: 'administration',
    scope: 'write',
    username: user,
    password: pass
};
```

The login service sends a POST request to the OAuth token endpoint and receives:
- `access_token`: JWT token for API requests
- `refresh_token`: Token for renewing access tokens
- `expires_in`: Token expiration time in seconds

### 2. Token Storage
Tokens are stored using multiple strategies:

**Cookie Storage (Primary)**:
- Tokens stored in HTTP-only cookies for security
- Automatic expiry based on token lifetime
- Path-specific cookies for proper scope

**In-Memory Storage (Fallback)**:
- Used when cookies are unavailable (e.g., SSR environments)
- Stored in the `bearerAuth` variable within the service

**Context Integration**:
- Tokens are synchronized with the global context store
- Available throughout the application via `Shopware.Context.api.authToken`

### 3. Request Authentication
All API requests are automatically authenticated:

**Authorization Header**:
```javascript
Authorization: `Bearer ${this.loginService.getToken()}`
```

**Language Context Header**:
```javascript
'sw-language-id': Shopware.Context.api.languageId
```

The API service automatically attaches these headers to every request, ensuring proper authentication and localization.

### 4. Token Refresh & Expiry Handling

**Automatic Refresh**:
- Tokens are automatically refreshed at half of their expiry time
- Refresh happens in the background without user interaction
- Uses the `refresh_token` grant type with the stored refresh token

**Expiry Detection**:
- Monitors token expiry timestamps
- Handles expired tokens gracefully with automatic refresh attempts
- Falls back to logout if refresh fails

**Cross-Tab Coordination (Web Locks API)**:
- When multiple browser tabs are open, only one tab performs the actual OAuth token refresh request
- This is achieved using the browser's [Web Locks API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Locks_API) under the lock key `sw-admin-token-refresh`
- Other tabs wait for the lock to be released and then check whether the token was already refreshed by the first tab
- Falls back gracefully to single-tab behavior when the Web Locks API is unavailable

**Exponential Backoff Retry**:
- Failed token refresh requests are retried with exponential backoff
- Default configuration: up to 2 retries, starting at 500 ms, doubling each attempt
- All retries exhausted → triggers inactivity logout and notifies all error subscribers

**In-Flight Deduplication**:
- Within a single tab, concurrent calls to `refreshToken()` share the same in-flight promise
- No duplicate HTTP requests are sent for overlapping refresh triggers

**User Activity Monitoring**:
- Tracks user activity to prevent unnecessary token refreshes
- Implements 30-minute inactivity threshold
- Triggers automatic logout for inactive sessions (unless "Remember Me" is enabled)

## Context Management

### Context Store Structure
The context store is implemented as a reactive Vue composition using `useContext()`:

```typescript
interface ContextState {
    app: {
        config: {
            adminWorker: WorkerConfig | null;
            bundles: BundleConfig | null;
            version: string | null;
            // ... other app configuration
        };
        environment: 'development' | 'production' | 'testing' | null;
        features: Record<string, boolean> | null;
        // ... other app state
    };
    api: {
        apiPath: string | null;
        authToken: ApiAuthToken | null;
        languageId: string | null;
        currencyId: string | null;
        // ... other API context
    };
}
```

### Context Updates
The context can be updated through dedicated methods:

- `addAppValue()`: Updates application-level context
- `addApiValue()`: Updates API-level context  
- `addAppConfigValue()`: Updates app configuration
- `setApiLanguageId()`: Changes the current language context

### Language Context Management
Special handling for language context:

- Current language ID stored in `localStorage` for persistence
- System default language tracking
- Automatic language header injection in API requests
- Language switching with context updates

## Security Features

### Token Security
- **HTTP-Only Cookies**: Prevents XSS attacks on token storage
- **Secure Transmission**: HTTPS-only token transmission
- **Limited Scope**: Tokens scoped to specific client and permissions
- **Automatic Expiry**: Time-based token expiration

### Session Security  
- **Inactivity Logout**: Automatic logout after 30 minutes of inactivity
- **Activity Tracking**: Monitors user interactions for session validation
- **Secure Cleanup**: Proper token cleanup on logout
- **Route Protection**: Session validation for protected routes

### Remember Me Feature
- **Optional Persistence**: User can choose extended session duration
- **Secure Storage**: Uses localStorage for remember me preference
- **Activity Override**: Bypasses inactivity logout when enabled
- **Extended TTL**: Uses refresh token TTL for extended sessions

## Event System

### Authentication Events
The login service provides event listeners for authentication state changes:

**Token Change Events**:
```javascript
loginService.addOnTokenChangedListener((authToken) => {
    // Handle token updates
});
```

**Login Events**:
```javascript
loginService.addOnLoginListener(() => {
    // Handle successful login
});
```

**Logout Events**:
```javascript
loginService.addOnLogoutListener(() => {
    // Handle logout cleanup
});
```

**Token Refresh Subscriber (HTTP interceptor pattern)**:

Use `subscribeToTokenRefresh` when you have queued requests that must be retried with the new token after a refresh completes. This is the pattern used internally by the HTTP factory to replay requests that received a 401 response while a refresh was already in progress.

```typescript
const loginService = Shopware.Service('loginService');

loginService.subscribeToTokenRefresh(
    (newToken: string) => {
        // Refresh succeeded — retry your queued request with the new token
        originalRequest.headers.Authorization = `Bearer ${newToken}`;
        resolve(httpClient(originalRequest));
    },
    (error: Error) => {
        // Refresh failed (all retries exhausted) — abort the queued request
        reject(error);
    },
);
```

Subscriber callbacks are **one-shot**: they are automatically cleared after the refresh cycle (success or failure) completes, so there is no need to unregister them manually.

**Checking refresh state**:

```typescript
const refreshInProgress: boolean = await loginService.isRefreshing();
```

`isRefreshing()` returns `true` if a refresh is currently in-flight either within the current tab or (where the Web Locks API is available) in another browser tab.

### Event Notifications
- **Automatic Notification**: Events are automatically triggered on state changes
- **Listener Management**: Multiple listeners can be registered for each event type
- **Context Synchronization**: Events ensure context store stays synchronized

## Error Handling

### Authentication Errors
- **Invalid Credentials**: Proper error propagation for login failures
- **Token Expiry**: Graceful handling of expired tokens
- **Network Errors**: Retry logic for temporary connection issues
- **Refresh Failures**: Automatic logout when refresh tokens are invalid

### Context Errors
- **Missing Context**: Fallback values for undefined context properties
- **Invalid State**: Validation and correction of inconsistent context state
- **Storage Errors**: Graceful degradation when storage is unavailable

## Integration Points

### Global Access
The authentication and context system is globally accessible:

```javascript
// Access context anywhere in the application
Shopware.Context.api.languageId
Shopware.Context.app.environment

// Access authentication service
Shopware.Service('loginService').getToken()
Shopware.Service('loginService').isLoggedIn()
```

### Route Guards
- Authentication state is checked by Vue Router guards
- Automatic redirection to login for unauthenticated users
- Session restoration after successful authentication

### API Integration
- All API services automatically use the authentication system
- Context information is included in API requests
- Consistent error handling across all API endpoints

This authentication and context system provides a robust foundation for the Shopware 6 Administration, ensuring secure API communication while maintaining a smooth user experience through automatic token management and context synchronization.
