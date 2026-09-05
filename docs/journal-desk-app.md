# LUNARA Journal Desk

The private app is served at `/journal-desk/` on the existing WordPress site. It reuses Journal Foundation, Dispatch, the active versioned editorial configuration, and the site's logged-in WordPress session. There is no second content database, token pasted into a browser, or additional hosting service.

## What is included

- Live paginated draft queue and search, sourced from the actual Journal content type.
- Headline, article, summary, deck, and search-description editing.
- Sources, featured image, publishing checks, and separate voice-review prompts.
- Revision proposals using the same active Journal voice and configured Dispatch provider. Proposals are compared and applied in the editor before any save.
- Explicit standing-instruction saves, versioned voice settings, source controls, and story-selection rules.
- Manual Dispatch runs with queued/running/completed status from the existing worker.
- Confirmed publishing through the existing permission and validation gates. Rejection retains the WordPress draft.
- Home Screen web-app manifest, responsive interface, and no caching of drafts in browser storage.

## Access and installation

Install this release of the existing `lunara-plugin-journal-foundation` plugin, preserving its current folder and database. The site was observed running Foundation 1.2.12 and Dispatch 3.2.7 during implementation; the implementation baseline was Foundation 1.2.14. Dispatch's existing scheduled worker is reused.

Open `/journal-desk/` while logged in as a WordPress administrator, or use **Journal → Open Journal Desk**. No permalink flush is required. On iPhone, open in Safari, use **Share → Add to Home Screen**, and enable **Open as Web App** when offered. An internet connection is required.

Publishing continues to honor `chatgpt.may_publish`, WordPress post capabilities, locked-draft restrictions, an explicit confirmation, and the Journal validator. The app does not change these settings or grant new bridge scopes. If publishing is disabled, its button links to the existing protected Journal Control Plane. Schedule and provider credentials remain in that existing settings page.

## Verification and limits

The standalone tests cover cookie/nonce permissions, configuration field allowlists, exact list replacement, stale revision conflicts, publish confirmation, provider boundaries, source-link restrictions, no rewrite persistence, and frontend action guards. Run the PHP files in `tests/` and `node --test tests/desk-state.test.cjs`; CI runs the PHP matrix on 7.4, 8.2, and 8.3.

The feature must still be exercised on the installed WordPress site: authenticated page load, opening a draft, saving, one provider rewrite, Dispatch completion, and an explicitly approved real publication. Local mocked HTTP tests do not establish live provider access. A passed formatting/metadata validator does not establish factual accuracy or editorial approval.

Per-draft tokens reject stale app requests; short locks serialize app-tab writes. Existing WordPress editor/legacy bridge writes do not share those locks, so these are not global database transactions. Failed or timed-out writes should be reloaded and checked before retrying. Session expiry can require a page reload; keep unsaved text before leaving.
