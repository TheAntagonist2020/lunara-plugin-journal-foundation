# LUNARA Journal Foundation

Version: 1.2.2

The Foundation owns the `journal` content model, ACF fields, versioned WordPress Control Plane, draft-first scope-gated security, provenance, validation, and the Fast Journal Desk used by the private LUNARA GPT.

## Fast Journal Desk

Use the included OpenAPI schema:

`openapi/lunara-journal-fast-desk.openapi.json`

Authentication remains a separate GPT Action setting:

`X-Lunara-Bridge-Token: {ChatGPT Editorial Bridge key}`

The key is never stored in the JSON schema.

Consolidated operations:

- `GET /journal/desk` — identity, health, Control Plane summary, Dispatch state, newest drafts, attention items, and the recommended next draft in one cached call.
- `GET /journal/desk/drafts/{id}` — complete editing workspace with active editorial rules and current validation, without the full audit log.
- `POST /journal/desk/drafts/{id}/save-validate` — save Dalton-approved fields and run deterministic validation in one call.
- `POST /journal/desk/run-dispatch` — queue a manual Dispatch run asynchronously and return immediately.
- `POST /dispatch/drafts/{id}/mark-ready` — mark a validated draft ready while preserving WordPress draft status.
- `GET /dispatch/drafts/{id}/audit` — retrieve full provenance only when explicitly needed.
- `POST /journal/desk/drafts/{id}/publish` — optional single-entry publish action. It is disabled by default, absent from the standard ChatGPT editor scope, and still requires validation plus explicit confirmation.

## Featured Image Guard

Every Fast Desk draft now carries deterministic featured-image diagnostics.

Hard validation failures:

- no featured image attached
- missing or unresolved media-library attachment
- featured media is not an image
- image URL or dimensions cannot be resolved
- image is smaller than 800x450

Quality warnings:

- image is below the preferred 1200x630 resolution
- aspect ratio falls outside the landscape-friendly 1.50-2.10 range
- attachment alt text is missing

`openJournalDesk` reports compact image status and aggregate image counts. `openJournalWorkspace` returns full dimensions, MIME type, URL, aspect ratio, alt text, hard errors, and warnings. `saveAndValidateJournalDraft` and `markJournalDraftReady` fail when the image is missing or unusable.

## Speed behavior

- The Journal Desk snapshot is cached for 60 seconds.
- Active configuration is cached within each WordPress request.
- Compiled prompts are memoized within each WordPress request.
- Normal workspace responses omit full audit history.
- Manual Dispatch runs are queued in WordPress rather than holding the GPT Action request open during feed, AI, and image processing.

## Access profiles

The `chatgpt_editor` profile has these scopes:

`read`, `update`, `validate`, `mark_ready`, `run_dispatch`, `audit`, `schema`

Existing ChatGPT keys automatically receive the `run_dispatch` scope when the profile registry is loaded, and any inherited `publish` scope is removed from the standard editor profile. Scoped keys do not need to be regenerated.

## Safety

The standard ChatGPT Editorial Bridge key refuses:

- publish
- schedule/future
- delete/trash
- WordPress post-status changes
- published, scheduled, or trashed content
- external Control Plane activation or rollback

The dedicated publish route remains available only to an explicitly publish-scoped key or a WordPress user who can both edit the selected entry and publish posts. Control Plane publishing is off by default.

Authentication is header-only. Send access keys with `X-Lunara-Bridge-Token`; query-string tokens are rejected.

## Legacy migration safety

- Auto-conversion defaults to off and no conversion cron is created during activation.
- Saved-post conversion runs only after an administrator explicitly enables it.
- Bulk legacy conversion requires a read-only preview followed by the exact confirmation phrase shown in WordPress.
- Only IDs preserved in the current server-side preview are eligible for the confirmed conversion.
- The conversion completion marker is written only after the post type change is successfully read back as `journal`.

## Dependencies

- WordPress 6.4 or newer.
- ACF Pro for the fully editable Journal field interface.
- Lunara Dispatch 3.2.0 or newer for automated collection and Fast Desk runs.
- Journal protocol 1.x compatibility between Foundation and Dispatch.

WordPress remains the authoritative runtime. The private GPT is the daily editorial interface. Notion is optional and is not required by Fast Journal Desk.

## Install order

1. Replace the existing LUNARA Journal Foundation with version 1.2.2.
2. Replace Lunara Dispatch Automation with version 3.2.0.
3. For production, update the private GPT Action schema using `openapi/lunara-journal-fast-desk.openapi.json`.
4. For staging, use `openapi/lunara-journal-fast-desk.staging.openapi.json` and replace its staging host variable before importing it.
5. Replace the GPT instructions with the supplied v1.2.2 instructions.
6. Keep the existing ChatGPT Editorial Bridge custom header. Reissue scoped keys if the installation still uses the retired legacy wildcard token.
