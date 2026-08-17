# Changelog

All notable changes to `pageweaver/sdk` are documented here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/). This package is versioned via git tags
(Packagist convention), not a `version` field in `composer.json`.

## 0.2.1 - 2026-08-18

### Changed

- Renamed `PageWeaverApiException` to `PageWeaverAPIException` (all-caps `API`) for casing
  consistency with the other PageWeaver SDKs. Every subclass (`PageWeaverAuthenticationException`,
  `PageWeaverPermissionException`, `PageWeaverNotFoundException`, `PageWeaverConflictException`,
  `PageWeaverValidationException`, `PageWeaverPlanRequiredException`, `PageWeaverRateLimitException`,
  `PageWeaverServerException`) is unaffected. Update any `catch (PageWeaverApiException $e)` clauses.

## 0.2.0 - 2026-08-18

### Added

- Full parity with the live `/v1` API: `ObjectTypes`, `Objects`, `RelationshipTypes`, `Search`,
  `WorkflowDefinitions`, `FormTemplates`, `Intake` (+ `IntakeSessions` with chunked/batch upload),
  `ErrorCodes`, `Events`.
- `Documents::validate`, `trust`, `diff`, `appendVersion`, `versions`, `version`, `representations`.
- Typed error hierarchy off `PageWeaverApiException`: `PageWeaverValidationException`,
  `PageWeaverAuthenticationException`, `PageWeaverPlanRequiredException`,
  `PageWeaverPermissionException` (`isScopeMissing()`/`getRequiredScope()`),
  `PageWeaverNotFoundException`, `PageWeaverConflictException`, `PageWeaverRateLimitException`,
  `PageWeaverServerException`, plus `PageWeaverInvalidRequestException` for client-side checks.
- Automatic retry with exponential backoff + jitter on `429`/`5xx`, honoring `Retry-After`, restricted
  to safe methods (`GET`/`HEAD`/`PUT`/`DELETE` always, `POST` only with an idempotency key).
- Client-side validation for blank ids, missing required fields, malformed bodies, and
  mutually-exclusive-field pairs.
- Multipart upload support via a new `Http::multipart()` method.

### Notes

- `localization.direction`, `parentMessageId`, and `gitRepo` needed no code changes here — request
  bodies are untyped arrays passed straight through. This SDK never had a `Projects` resource or a
  `LivingDocuments` resource to remove (a README migration note was added regardless, mapping the old
  REST calls to the new document-lineage calls).

## 0.1.0 - initial release

- Initial `pageweaver/sdk` release: documents, templates, schemas, environments, deployments, reviews,
  proposals, comments, share links, usage, webhooks.
