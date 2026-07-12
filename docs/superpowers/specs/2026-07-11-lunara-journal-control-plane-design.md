# LUNARA Journal Control Plane — Design Specification

**Date:** 2026-07-11  
**Status:** Proposed for implementation  
**Owner:** Dalton Johnson / LUNARA FILM  
**Runtime authority:** WordPress  
**Human operations mirror:** Notion  

## 1. Purpose

The LUNARA Journal is a core editorial system operated by one person. Its current behavior is distributed across Lunara Dispatch Automation, LUNARA Journal Foundation, provider settings, prompt text, a private GPT, WordPress options, ACF fields, and automation controls. That distribution creates ambiguity about which rule is active, who wrote or changed a draft, and where the operator should go to manage the system.

This project creates one authoritative WordPress administration surface named **Journal Control Plane**. The Control Plane owns the active runtime configuration for Dispatch, ChatGPT editorial access, validation, workflow, provider routing, source management, audit, and synchronization. Notion receives a one-way mirror for organization and operating visibility, but cannot activate production changes. ChatGPT can read the active configuration and edit eligible drafts, but cannot activate configuration versions, publish, schedule, delete, or change WordPress post status.

## 2. Approved Architectural Decisions

1. Keep **two coordinated plugins** rather than merging them:
   - **LUNARA Journal Foundation** owns the Journal data model, Control Plane, configuration versions, validation, access control, audit, and Notion synchronization.
   - **Lunara Dispatch Automation** owns feeds, source normalization, AI drafting, image handling, duplicate detection, quality gating, scheduling, retries, and generation diagnostics.
2. Expose both plugins through one visible administration surface:
   - `Journal → Control Plane`
3. WordPress is the only authoritative runtime configuration store.
4. Notion is a one-way, readable operations mirror.
5. ChatGPT retrieves the active configuration at runtime rather than relying on a copied editorial specification.
6. Neither Notion nor ChatGPT may activate, roll back, publish, schedule, delete, or otherwise mutate production state beyond explicitly authorized draft edits.
7. Manual publication by Dalton remains mandatory.
8. The system fails closed: an integration failure leaves content unpublished and configuration unchanged.

## 3. Current-Code Findings

### 3.1 Lunara Dispatch Automation 3.0.15

Dispatch currently owns these WordPress options:

- `lunara_dispatch_enabled`
- `lunara_dispatch_post_type`
- `lunara_dispatch_post_status`
- `lunara_dispatch_schedule`
- `lunara_dispatch_provider`
- `lunara_dispatch_max_tokens`
- Provider API keys and models for Claude, OpenAI, Gemini, and Grok
- `lunara_dispatch_voice_refinement`
- `lunara_dispatch_system_prompt_override`
- `lunara_dispatch_sources`
- Seen-source state and last-run diagnostics

Prompt precedence is currently:

1. Full Prompt Override, when present, replaces the default system prompt.
2. Voice / Prompt Refinement is appended afterward.
3. A separate static per-run directive is appended with the source payload.

Dispatch currently allows post statuses `draft`, `pending`, `private`, and `publish`. The unified design will remove `publish` from Dispatch's valid runtime behavior and force generated Journal items to `draft`.

### 3.2 LUNARA Journal Foundation 0.3.0

Foundation currently owns:

- `journal` CPT
- `journal_section` and `journal_topic` taxonomies
- ACF editorial fields
- Journal workflow fields
- Draft-only REST bridge
- Scoped identities for ChatGPT, Dispatch, and manual admin testing
- Audit logging
- Validation
- Dispatch draft conversion

Foundation is the correct owner for the Control Plane because it owns the content model, access layer, and workflow state that must remain available even when Dispatch is disabled.

## 4. System Boundaries

### 4.1 Journal Foundation responsibilities

Foundation will own:

- Canonical Journal configuration schema
- Immutable configuration versions
- Draft configuration workspace
- Activation and rollback
- Control Plane interface
- Journal CPT and ACF schema
- Deterministic validation rules
- Editorial workflow transitions
- Access profiles and token rotation
- ChatGPT REST/MCP-facing configuration endpoints
- Audit history
- Notion sync queue and sync state
- Health aggregation across Foundation and Dispatch

### 4.2 Dispatch responsibilities

Dispatch will own:

- RSS/feed source retrieval
- Feed parsing and normalization
- Seen-source and duplicate tracking
- Editorial candidate selection
- AI-provider request execution
- Generated-output parsing
- Image discovery, eligibility, sideloading, and assignment
- Topic duplicate detection
- Runtime quality gate
- Draft creation
- Cron execution and manual Run Now
- Generation reports and failure diagnostics

Dispatch will no longer independently decide active editorial rules, output post type, or publish status. It will consume those values through the Foundation configuration service.

### 4.3 Notion responsibilities

Notion will display:

- Active configuration summary
- Active configuration version
- Editorial specification
- Provider/model routing without secrets
- Source inventory
- Workflow definition
- Validation checklist
- Current health summary
- Recent configuration change log
- Links back to the WordPress Control Plane

Notion will not:

- Store runtime secrets
- Activate or roll back configuration
- Change providers or models
- Change source enablement
- Change schedules
- Publish or edit Journal posts
- Mutate WordPress workflow state

## 5. Control Plane Information Architecture

The WordPress menu will be:

- **Journal**
  - All Journal Entries
  - Add New
  - **Control Plane**
  - Sections
  - Topics

The legacy `Settings → Lunara Dispatch` page will become a read-only compatibility/diagnostics page with a prominent link to `Journal → Control Plane`. Existing form fields will no longer be editable after migration succeeds.

### 5.1 Dashboard

The first screen answers four questions immediately:

1. Is the system healthy?
2. What configuration version is active?
3. When did Dispatch last run and what happened?
4. What needs Dalton's attention?

Dashboard cards:

- Active configuration version
- Automation enabled/disabled
- Last Dispatch run and result
- Next scheduled run
- Queue counts by Journal workflow state
- Validation failures
- Notion sync state
- ChatGPT bridge health
- Provider connectivity status
- Recent audit activity

### 5.2 Editorial Specification

A structured editor will manage:

- Journal purpose
- Audience stance
- Selection gate
- Voice principles
- Hook requirements
- Attribution rules
- Inference/uncertainty language
- Structure rules
- Formatting rules
- Headline rules
- Landing rules
- Source-risk rules
- Banned phrases
- Required metadata
- Validation rules

The UI will show both:

- Structured fields, which are authoritative
- A read-only compiled prompt preview generated from those fields

The active prompt will never be manually maintained in multiple text areas.

### 5.3 Automation

Controls:

- Automation enabled
- Schedule
- Manual Run Now
- Draft-only enforcement indicator
- Maximum items per run
- Maximum Journal entries per run
- Duplicate lookback window
- Retry behavior
- Last and next run

`Target Post Type` will be fixed to `journal` and displayed read-only. `Publish Mode` will be fixed to `Draft` and displayed read-only.

### 5.4 AI Providers

Controls:

- Initial drafting provider
- Initial drafting model
- Optional fallback provider/model
- Maximum output tokens
- Request timeout
- Retry count
- Provider connectivity tests
- Masked secret status

Provider API keys remain encrypted/secured WordPress options or server configuration. They are never returned through Notion, ChatGPT configuration endpoints, health endpoints, or logs.

### 5.5 Sources

Each source record will include:

- Stable source ID
- Label
- Feed URL
- Enabled
- Priority
- Maximum items per run
- Trust/risk classification
- Image reuse policy
- Allowed Journal sections
- Last successful fetch
- Last error

Source configuration is versioned with the rest of the Control Plane.

### 5.6 Workflow and Validation

Internal Journal workflow values:

- `collected`
- `dispatch_generated`
- `needs_chatgpt_review`
- `chatgpt_reviewed`
- `validation_failed`
- `ready_for_dalton`
- `editor_approved`
- `held`
- `rejected`
- `published`

WordPress post status remains independent. Before publication, the WordPress status must remain `draft` regardless of internal workflow state.

The deterministic validator will check at minimum:

- Post type is `journal`
- WordPress post status is `draft`
- Title exists and meets configured length rules
- Body exists and meets minimum content rules
- No `<h2>` tags
- Film titles use `<em>` where supplied/known
- Prohibited HTML is absent
- Source URL is present
- Source provenance is stored
- Featured image is present when required
- Image alt text is present when required
- Excerpt/deck is present
- SEO description is present
- Journal section is assigned
- Required ACF fields are populated
- Banned phrases are absent
- Configuration version is attached
- Provider/model provenance is attached

AI may supplement editorial evaluation, but deterministic validation controls workflow eligibility.

### 5.7 Access and Audit

Access identities remain separate:

- `dispatch_ingest`
- `chatgpt_editor`
- `dalton_admin`

Only an authenticated WordPress administrator with `manage_options` can:

- Save a draft configuration
- Activate a configuration version
- Roll back configuration
- Change provider routing
- Change schedule or source enablement
- Generate/revoke access keys
- Trigger Notion resynchronization

Every write records:

- Actor
- Client
- Action
- Timestamp
- Configuration version
- Target object
- Before/after summary where applicable
- Result

Secrets and complete prompt payloads are excluded from routine logs.

## 6. Canonical Configuration Model

The configuration is stored as a versioned JSON-compatible document. A representative shape is:

```json
{
  "schema_version": "1.0.0",
  "configuration_version": "1.0.0",
  "state": "active",
  "editorial": {
    "purpose": "",
    "selection": {},
    "voice": {},
    "structure": {},
    "formatting": {},
    "banned_phrases": [],
    "source_risk_rules": {}
  },
  "automation": {
    "enabled": true,
    "schedule": "daily",
    "post_type": "journal",
    "post_status": "draft",
    "maximum_entries": 3,
    "retry_count": 1
  },
  "providers": {
    "drafting": {
      "provider": "openai",
      "model": "",
      "max_tokens": 4096
    },
    "fallback": null
  },
  "sources": [],
  "validation": {},
  "workflow": {},
  "notion": {
    "enabled": false,
    "target_page_id": "",
    "last_synced_version": ""
  }
}
```

Secrets are referenced by provider key identifiers and stored separately. Configuration exports and Notion mirrors never contain secret values.

## 7. Configuration Lifecycle

Configuration states:

- `draft`
- `testing`
- `active`
- `superseded`
- `rolled_back`

Lifecycle:

1. Dalton edits a draft configuration.
2. Saving creates or updates an unactivated draft version.
3. Test Configuration validates schema, compiles prompts, checks provider availability, checks source URLs, and runs a dry-run without creating posts.
4. Activation requires an explicit WordPress administrator action and nonce confirmation.
5. Activation atomically marks the new version active and the former version superseded.
6. Notion synchronization is queued after successful activation.
7. Rollback creates a new active version cloned from a previous version; history is never rewritten.

ChatGPT and Notion receive no activation endpoint.

## 8. Runtime Data Flow

### 8.1 Dispatch run

1. Dispatch requests the active configuration from Foundation through an internal PHP service, not an HTTP loopback call.
2. Foundation returns the active immutable configuration snapshot and compiled prompt.
3. Dispatch records the configuration version for the run.
4. Dispatch fetches and normalizes enabled sources.
5. Dispatch sends the compiled prompt and source payload to the configured provider.
6. Dispatch applies parser, duplicate, image, and quality gates.
7. Dispatch creates `journal` posts with WordPress status `draft`.
8. Each created draft receives configuration, provider, model, source, run, and plugin-version provenance.
9. The Journal workflow becomes `needs_chatgpt_review`.
10. Run diagnostics are reported to the Control Plane.

### 8.2 ChatGPT editorial session

1. ChatGPT authenticates as `chatgpt_editor`.
2. ChatGPT retrieves bridge identity, health, and active configuration version.
3. ChatGPT retrieves a selected eligible draft.
4. ChatGPT proposes changes without saving until Dalton approves.
5. On approval, ChatGPT updates only allowlisted draft fields.
6. Foundation records attribution and revision provenance.
7. ChatGPT invokes deterministic validation.
8. Passing drafts may be marked `ready_for_dalton`; failing drafts become `validation_failed`.
9. WordPress post status remains `draft`.

### 8.3 Publication

1. Dalton opens the draft in WordPress.
2. Dalton reviews content, source provenance, validation report, image, and audit history.
3. Dalton explicitly publishes through WordPress.
4. Foundation records the human publisher and final active configuration version.
5. ChatGPT and Dispatch have no publication capability.

## 9. ChatGPT Integration Contract

The ChatGPT-facing API will expose:

- Identity
- Health
- Active configuration summary
- Active editorial specification
- Configuration version
- Draft listing
- Draft retrieval
- Allowlisted draft update
- Validation
- Mark ready
- Draft audit

It will not expose:

- Provider secrets
- Notion secret
- Configuration activation
- Configuration rollback
- Source mutation
- Schedule mutation
- Publication
- Deletion/trash
- Arbitrary post-status changes

The private GPT's static instructions will be reduced to security and workflow invariants. It must retrieve the active specification before editorial work and cite the configuration version in its operational summary.

## 10. One-Way Notion Synchronization

### 10.1 Trigger

A sync job is queued when:

- A configuration version is activated
- Dalton manually clicks Resync
- A previous sync failed and retry is due

### 10.2 Payload

The Notion mirror contains:

- Active version and activation time
- Editorial specification
- Compiled prompt preview or structured summary
- Automation configuration
- Provider/model names without keys
- Source inventory
- Workflow states
- Validation rules
- Health snapshot
- Change summary
- WordPress Control Plane link

### 10.3 Safety

- Sync is one-way from WordPress to Notion.
- Notion cannot call an activation route.
- The Notion credential has only the minimum page/database permissions required.
- Sync writes are idempotent by configuration version.
- A Notion failure never blocks configuration activation or Dispatch.
- Failed syncs enter a retry queue and surface an admin notice.
- No API keys, bridge tokens, or full sensitive request logs are sent to Notion.

## 11. Migration

Migration is non-destructive and staged.

### Stage 1: Inventory and snapshot

- Export current Dispatch options.
- Snapshot current Foundation options and access profiles.
- Record current sources, prompts, provider/model choices, schedule, post type, and post status.

### Stage 2: Create initial canonical version

- Convert current default/override/refinement prompt behavior into one structured specification.
- Import sources.
- Import provider/model routing without moving or exposing secret values.
- Force `journal` and `draft` in the canonical runtime configuration.
- Create configuration version `1.0.0` as inactive draft.

### Stage 3: Dry-run verification

- Compile the canonical prompt.
- Compare it against current effective Dispatch prompt.
- Run provider connection checks.
- Run source checks.
- Execute a dry run without post creation.

### Stage 4: Activation

- Dalton explicitly activates `1.0.0`.
- Dispatch begins reading the Foundation configuration service.
- Legacy Dispatch settings become read-only.

### Stage 5: Controlled acceptance run

- Run one Dispatch cycle.
- Create at most one test draft.
- Perform one ChatGPT revision.
- Validate.
- Mark ready.
- Publish manually only after inspection.

### Stage 6: Notion mirror

- Configure Notion target and credential.
- Perform first one-way sync.
- Verify version and content.

Legacy settings remain stored during the stabilization period to permit diagnostic comparison, but are no longer authoritative.

## 12. Error Handling and Failure Modes

- **No active configuration:** Dispatch refuses to run and reports a Control Plane error.
- **Malformed configuration:** Activation is blocked; current active version remains unchanged.
- **Provider unavailable:** Run fails without creating drafts; retry policy applies.
- **Feed failure:** Other sources continue; source-specific errors are recorded.
- **Malformed AI output:** No draft is created for the malformed entry.
- **Validation failure:** Draft remains unpublished and enters `validation_failed`.
- **ChatGPT unavailable:** Draft remains awaiting review.
- **Notion unavailable:** Runtime continues; sync retries later.
- **Unauthorized API call:** Request is denied and audit event recorded without leaking credentials.
- **Configuration activation race:** Atomic locking prevents two simultaneous activations.
- **Plugin mismatch:** Health page shows incompatible versions; Dispatch refuses configuration-dependent runs when compatibility is unsafe.

## 13. Compatibility and Upgrade Strategy

Foundation and Dispatch will declare compatible Control Plane protocol versions. Example:

- Foundation protocol: `1.x`
- Dispatch supported protocol: `^1.0`

The Control Plane health screen will show:

- Foundation plugin version
- Dispatch plugin version
- Configuration schema version
- Protocol compatibility
- Active configuration version

Database/configuration migrations are versioned, idempotent, and executed only by a WordPress administrator. No migration deletes Journal content.

## 14. Testing Strategy

### Unit-level tests

- Configuration schema validation
- Prompt compilation
- Secret redaction
- Version activation and rollback
- Workflow transition rules
- Deterministic Journal validation
- Source normalization
- Notion payload generation
- Access-scope enforcement

### Integration tests

- Foundation internal configuration service consumed by Dispatch
- Dispatch creates `journal` draft only
- Provenance attached correctly
- ChatGPT allowlisted update
- ChatGPT denied activation/publish/delete
- Notion one-way sync
- Notion outage does not block runtime
- Legacy setting migration

### Acceptance tests

1. Create initial configuration version.
2. Test without activation.
3. Activate explicitly as administrator.
4. Run Dispatch once.
5. Confirm one Journal draft and complete provenance.
6. Review through private GPT.
7. Save approved revision.
8. Validate.
9. Mark ready for Dalton.
10. Publish manually.
11. Verify audit trail.
12. Verify Notion mirror matches active version.
13. Roll back to a prior version in a controlled test and confirm a new version is created rather than history being rewritten.

## 15. Success Criteria

The project is complete when:

- Dalton can manage the complete Journal runtime from `Journal → Control Plane`.
- No active editorial rule must be maintained in more than one place.
- Dispatch reads the active canonical configuration from Foundation.
- Dispatch cannot publish.
- ChatGPT retrieves the active configuration before editorial work.
- ChatGPT cannot activate configuration or publish/delete content.
- Notion mirrors the active configuration automatically and cannot write back.
- Every Journal draft identifies source, configuration version, provider, model, generation actor, revision actor, validation result, and human publisher.
- Configuration versions can be tested, activated, compared, and rolled back safely.
- Existing Journal content and the existing `/journal/` page remain intact.
- One end-to-end acceptance run succeeds without manual setting duplication.

## 16. Explicit Non-Goals for the First Release

- Two-way Notion configuration editing
- Automatic publication
- Autonomous ChatGPT background editing without Dalton's approval
- Replacing WordPress with Notion as the runtime datastore
- Rebuilding the Dispatch feed and generation engine from scratch
- General site-wide AI governance outside the Journal
- Multi-user editorial approvals beyond Dalton's administrator approval

