# LUNARA Journal Control Plane Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build one authoritative, versioned WordPress Journal Control Plane that governs Dispatch, ChatGPT, validation, provenance, and a one-way Notion mirror while preserving manual publication and the existing Journal content model.

**Architecture:** `LUNARA Journal Foundation` owns the canonical configuration, lifecycle, workflow, validation, audit, ChatGPT read/edit contract, and Notion synchronization. `Lunara Dispatch Automation` remains the feed and generation engine but consumes the active Foundation configuration through a direct PHP interface rather than maintaining independent editorial settings. Active configuration changes and rollbacks are available only to authenticated WordPress administrators.

**Tech Stack:** WordPress 6.4+, PHP 7.4+, ACF Pro, WordPress REST API, WP-Cron, WordPress HTTP API, Notion REST API `2026-03-11`, PHPUnit 9.6, Brain Monkey 2.6, Mockery 1.6, vanilla WordPress admin PHP/CSS/JavaScript.

## Global Constraints

- Preserve the public page at `/journal/`; the `journal` CPT must continue using `has_archive => false`.
- Preserve every existing Journal post, taxonomy term, media relationship, revision, and post ID.
- Dispatch-created content must always use `post_type = journal` and `post_status = draft`.
- Only a logged-in WordPress administrator with `manage_options` may activate or roll back configuration.
- ChatGPT may read active configuration, read/update allowlisted draft fields, validate, inspect audit history, and mark a draft ready; it may not activate configuration, mutate sources or schedules, publish, schedule, trash, or delete.
- Notion synchronization is one-way from WordPress and never blocks configuration activation or Dispatch runtime.
- Provider secrets, bridge tokens, and the Notion token remain in separate WordPress options and never appear in configuration JSON, exports, audit payloads, REST responses, or Notion content.
- Active configuration versions are immutable. Rollback creates a new version cloned from a prior version.
- Configuration protocol version is `1.0.0`; schema version is `1.0.0`.
- Foundation release target is `1.0.0`; Dispatch release target is `3.1.0`.
- No new production PHP package dependency is introduced; Composer dependencies are development-only.
- Existing legacy settings remain stored during stabilization but become non-authoritative after Control Plane activation.

## Target Workspace and File Structure

```text
plugins/
├── lunara-journal-foundation/
│   ├── lunara-journal-foundation.php
│   ├── includes/
│   │   ├── class-lunara-journal-protocol.php
│   │   ├── class-lunara-journal-config-schema.php
│   │   ├── class-lunara-journal-config-repository.php
│   │   ├── class-lunara-journal-prompt-compiler.php
│   │   ├── class-lunara-journal-control-plane.php
│   │   ├── class-lunara-journal-control-plane-admin.php
│   │   ├── class-lunara-journal-control-plane-tester.php
│   │   ├── class-lunara-journal-migration.php
│   │   ├── class-lunara-journal-provenance.php
│   │   ├── class-lunara-journal-workflow.php
│   │   ├── class-lunara-journal-validator.php
│   │   ├── class-lunara-journal-health.php
│   │   ├── class-lunara-journal-notion-client.php
│   │   └── class-lunara-journal-notion-sync.php
│   ├── assets/admin/control-plane.css
│   ├── assets/admin/control-plane.js
│   ├── openapi/lunara-journal-bridge.openapi.json
│   ├── composer.json
│   ├── phpunit.xml.dist
│   └── tests/
│       ├── bootstrap.php
│       ├── test-protocol.php
│       ├── test-config-schema.php
│       ├── test-config-repository.php
│       ├── test-prompt-compiler.php
│       ├── test-workflow.php
│       ├── test-validator.php
│       ├── test-rest-permissions.php
│       ├── test-notion-payload.php
│       └── test-migration.php
└── lunara-dispatch/
    ├── lunara-dispatch.php
    ├── includes/
    │   ├── class-control-plane-client.php
    │   ├── class-run-context.php
    │   ├── class-admin.php
    │   ├── class-ai-client.php
    │   ├── class-plugin.php
    │   ├── class-post-builder.php
    │   ├── class-prompts.php
    │   └── class-sources.php
    ├── composer.json
    ├── phpunit.xml.dist
    └── tests/
        ├── bootstrap.php
        ├── test-control-plane-client.php
        ├── test-draft-only.php
        ├── test-runtime-routing.php
        └── test-provenance.php
```

---

### Task 1: Add test harnesses and protocol constants

**Files:**
- Create: `plugins/lunara-journal-foundation/composer.json`
- Create: `plugins/lunara-journal-foundation/phpunit.xml.dist`
- Create: `plugins/lunara-journal-foundation/tests/bootstrap.php`
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-protocol.php`
- Create: `plugins/lunara-journal-foundation/tests/test-protocol.php`
- Create: `plugins/lunara-dispatch/composer.json`
- Create: `plugins/lunara-dispatch/phpunit.xml.dist`
- Create: `plugins/lunara-dispatch/tests/bootstrap.php`

**Interfaces:**
- Produces: `Lunara_Journal_Protocol::VERSION`, `SCHEMA_VERSION`, `is_compatible(string): bool`, and `health(): array`.
- Consumes: no earlier implementation tasks.

- [ ] **Step 1: Add the Foundation development dependencies**

```json
{
  "name": "lunara/journal-foundation",
  "description": "Development dependencies for LUNARA Journal Foundation.",
  "type": "wordpress-plugin",
  "require-dev": {
    "brain/monkey": "^2.6",
    "mockery/mockery": "^1.6",
    "phpunit/phpunit": "^9.6"
  },
  "autoload-dev": {
    "classmap": ["includes/", "tests/"]
  },
  "config": {
    "sort-packages": true
  }
}
```

- [ ] **Step 2: Add the Foundation PHPUnit configuration**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" failOnWarning="true">
    <testsuites>
        <testsuite name="LUNARA Journal Foundation">
            <directory suffix=".php">tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Add the Foundation test bootstrap**

```php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

require_once dirname(__DIR__) . '/includes/class-lunara-journal-protocol.php';
```

- [ ] **Step 4: Write the failing protocol tests**

```php
<?php
use PHPUnit\Framework\TestCase;

final class Test_Lunara_Journal_Protocol extends TestCase {
    public function test_protocol_reports_exact_versions(): void {
        self::assertSame('1.0.0', Lunara_Journal_Protocol::VERSION);
        self::assertSame('1.0.0', Lunara_Journal_Protocol::SCHEMA_VERSION);
    }

    public function test_protocol_accepts_same_major_version(): void {
        self::assertTrue(Lunara_Journal_Protocol::is_compatible('1.4.2'));
        self::assertFalse(Lunara_Journal_Protocol::is_compatible('2.0.0'));
        self::assertFalse(Lunara_Journal_Protocol::is_compatible('invalid'));
    }
}
```

- [ ] **Step 5: Run the protocol test and verify failure**

Run:

```bash
cd plugins/lunara-journal-foundation
composer install
vendor/bin/phpunit tests/test-protocol.php
```

Expected: FAIL because `Lunara_Journal_Protocol` is not defined.

- [ ] **Step 6: Implement the protocol class**

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Lunara_Journal_Protocol {
    public const VERSION = '1.0.0';
    public const SCHEMA_VERSION = '1.0.0';

    public static function is_compatible(string $consumer_version): bool {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $consumer_version, $matches)) {
            return false;
        }

        return (int) $matches[1] === 1;
    }

    public static function health(): array {
        return array(
            'protocol_version' => self::VERSION,
            'schema_version'   => self::SCHEMA_VERSION,
        );
    }
}
```

- [ ] **Step 7: Add the matching Dispatch development harness**

Use the same `composer.json`, `phpunit.xml.dist`, and `tests/bootstrap.php` structure, changing the package name to `lunara/dispatch` and loading `includes/class-control-plane-client.php` only after Task 9 creates it.

- [ ] **Step 8: Run the Foundation test suite**

Run:

```bash
vendor/bin/phpunit
```

Expected: 2 tests pass.

- [ ] **Step 9: Commit**

```bash
git add plugins/lunara-journal-foundation/composer.json \
        plugins/lunara-journal-foundation/phpunit.xml.dist \
        plugins/lunara-journal-foundation/tests \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-protocol.php \
        plugins/lunara-dispatch/composer.json \
        plugins/lunara-dispatch/phpunit.xml.dist \
        plugins/lunara-dispatch/tests/bootstrap.php
git commit -m "test: establish journal control plane harness"
```

---

### Task 2: Implement the canonical configuration schema

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-config-schema.php`
- Create: `plugins/lunara-journal-foundation/tests/test-config-schema.php`

**Interfaces:**
- Consumes: `Lunara_Journal_Protocol::SCHEMA_VERSION`.
- Produces: `defaults(): array`, `sanitize(array): array`, `validate(array): array`, and `redact_secrets(array): array`.

- [ ] **Step 1: Write schema tests for fixed runtime invariants and secret redaction**

```php
<?php
use PHPUnit\Framework\TestCase;

final class Test_Lunara_Journal_Config_Schema extends TestCase {
    public function test_defaults_force_journal_drafts(): void {
        $config = Lunara_Journal_Config_Schema::defaults();
        self::assertSame('journal', $config['automation']['post_type']);
        self::assertSame('draft', $config['automation']['post_status']);
        self::assertSame(3, $config['automation']['maximum_entries']);
        self::assertFalse($config['notion']['enabled']);
    }

    public function test_sanitize_cannot_enable_publication(): void {
        $config = Lunara_Journal_Config_Schema::sanitize(array(
            'automation' => array(
                'post_type'   => 'post',
                'post_status' => 'publish',
            ),
        ));
        self::assertSame('journal', $config['automation']['post_type']);
        self::assertSame('draft', $config['automation']['post_status']);
    }

    public function test_validation_rejects_unknown_provider(): void {
        $config = Lunara_Journal_Config_Schema::defaults();
        $config['providers']['drafting']['provider'] = 'unknown';
        $result = Lunara_Journal_Config_Schema::validate($config);
        self::assertFalse($result['valid']);
        self::assertContains('providers.drafting.provider must be one of claude, openai, gemini, grok.', $result['errors']);
    }

    public function test_redaction_removes_secret_values(): void {
        $config = Lunara_Journal_Config_Schema::defaults();
        $config['providers']['drafting']['api_key'] = 'secret-value';
        $config['notion']['token'] = 'secret-notion';
        $redacted = Lunara_Journal_Config_Schema::redact_secrets($config);
        self::assertArrayNotHasKey('api_key', $redacted['providers']['drafting']);
        self::assertArrayNotHasKey('token', $redacted['notion']);
    }
}
```

- [ ] **Step 2: Run the schema tests and verify failure**

Run:

```bash
vendor/bin/phpunit tests/test-config-schema.php
```

Expected: FAIL because `Lunara_Journal_Config_Schema` is not defined.

- [ ] **Step 3: Implement the schema defaults and sanitization**

Create the class with this public contract and exact invariant enforcement:

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Lunara_Journal_Config_Schema {
    private const PROVIDERS = array('claude', 'openai', 'gemini', 'grok');
    private const SCHEDULES = array('daily', 'twice_daily', 'every_4_hours', 'every_2_hours');

    public static function defaults(): array {
        return array(
            'schema_version'        => Lunara_Journal_Protocol::SCHEMA_VERSION,
            'configuration_version' => '1.0.0',
            'state'                 => 'draft',
            'editorial'             => array(
                'purpose' => 'Selective film-news dispatches with a concrete LUNARA angle, written for readers rather than to satisfy a publishing quota.',
                'selection' => array(
                    'preferred_entries' => 2,
                    'maximum_entries'   => 3,
                    'minimum_words'     => 75,
                    'combine_related'   => true,
                    'skip_thin_items'   => true,
                ),
                'voice' => array(
                    'reader_interest_first' => true,
                    'critic_judgment_second' => true,
                    'concrete_language'      => true,
                    'active_verbs'           => true,
                    'press_release_tone'     => false,
                    'forced_balance'         => false,
                ),
                'structure' => array(
                    'headline_tag'       => 'h3',
                    'paragraphs_minimum' => 2,
                    'paragraphs_maximum' => 4,
                    'landing_required'   => true,
                ),
                'formatting' => array(
                    'output'         => 'html',
                    'film_title_tag' => 'em',
                    'allow_h2'       => false,
                    'allow_lists'    => false,
                    'allow_inline_css' => false,
                    'ascii_only'     => true,
                ),
                'banned_phrases' => array(
                    'autopsy',
                    'is not a movie',
                    'is not a film',
                    'ever-evolving',
                    'poised to',
                    'made waves',
                    'must-see',
                    'garnering attention',
                    'cinematic discourse',
                    'in the current landscape',
                    'raises significant questions',
                    'unprecedented',
                    'game-changer',
                    'a love letter to',
                    'at the forefront of',
                    'highly anticipated',
                    'this matters because',
                    'this is significant as',
                    'could potentially',
                    'part of the conversation',
                    'worth keeping an eye on',
                    'only time will tell',
                    'fans are eagerly awaiting',
                    'delves into',
                    'underscores',
                    'a testament to'
                ),
                'source_risk_rules' => array(
                    'world-of-reel' => array(
                        'image_reuse'              => false,
                        'headline_mimicry'         => false,
                        'requires_original_angle'  => true,
                        'requires_natural_credit'  => true,
                    ),
                ),
            ),
            'automation' => array(
                'enabled'         => true,
                'schedule'        => 'daily',
                'post_type'       => 'journal',
                'post_status'     => 'draft',
                'maximum_entries' => 3,
                'retry_count'     => 1,
            ),
            'providers' => array(
                'drafting' => array(
                    'provider'       => 'openai',
                    'model'          => 'gpt-4o',
                    'max_tokens'     => 4096,
                    'credential_ref' => 'lunara_dispatch_openai_key',
                ),
                'fallback' => null,
            ),
            'sources' => array(),
            'validation' => array(
                'source_url_required'      => true,
                'featured_image_required'  => true,
                'excerpt_required'         => true,
                'seo_description_required' => true,
                'minimum_words'            => 75,
                'minimum_paragraphs'       => 2,
                'disallow_h2'              => true,
                'film_titles_use_em'       => true,
                'ascii_only'               => true,
            ),
            'workflow' => array(
                'initial_state' => 'needs_chatgpt_review',
                'states' => array(
                    'collected',
                    'drafted',
                    'needs_chatgpt_review',
                    'validation_failed',
                    'ready_for_dalton',
                    'published',
                    'rejected'
                ),
                'human_publication_required' => true,
            ),
            'notion' => array(
                'enabled'             => false,
                'target_page_id'      => '',
                'last_synced_version' => '',
            ),
        );
    }

    public static function sanitize(array $input): array {
        $config = array_replace_recursive(self::defaults(), $input);
        $config['schema_version'] = Lunara_Journal_Protocol::SCHEMA_VERSION;
        $config['state'] = in_array($config['state'], array('draft', 'testing', 'active', 'superseded', 'rolled_back'), true)
            ? $config['state']
            : 'draft';
        $config['automation']['enabled'] = !empty($config['automation']['enabled']);
        $config['automation']['schedule'] = in_array($config['automation']['schedule'], self::SCHEDULES, true)
            ? $config['automation']['schedule']
            : 'daily';
        $config['automation']['post_type'] = 'journal';
        $config['automation']['post_status'] = 'draft';
        $config['automation']['maximum_entries'] = max(1, min(3, (int) $config['automation']['maximum_entries']));
        $config['automation']['retry_count'] = max(0, min(3, (int) $config['automation']['retry_count']));
        $config['providers']['drafting']['provider'] = sanitize_key((string) $config['providers']['drafting']['provider']);
        $config['providers']['drafting']['model'] = sanitize_text_field((string) $config['providers']['drafting']['model']);
        $config['providers']['drafting']['max_tokens'] = max(1024, min(16000, (int) $config['providers']['drafting']['max_tokens']));
        $config['notion']['enabled'] = !empty($config['notion']['enabled']);
        $config['notion']['target_page_id'] = preg_replace('/[^a-f0-9-]/i', '', (string) $config['notion']['target_page_id']);
        $config['sources'] = self::sanitize_sources((array) $config['sources']);
        unset($config['providers']['drafting']['api_key'], $config['notion']['token']);
        return $config;
    }

    public static function validate(array $input): array {
        $config = self::sanitize($input);
        $errors = array();

        if (!in_array($config['providers']['drafting']['provider'], self::PROVIDERS, true)) {
            $errors[] = 'providers.drafting.provider must be one of claude, openai, gemini, grok.';
        }
        if ($config['providers']['drafting']['model'] === '') {
            $errors[] = 'providers.drafting.model is required.';
        }
        if ($config['automation']['post_type'] !== 'journal') {
            $errors[] = 'automation.post_type must be journal.';
        }
        if ($config['automation']['post_status'] !== 'draft') {
            $errors[] = 'automation.post_status must be draft.';
        }
        if ($config['notion']['enabled'] && $config['notion']['target_page_id'] === '') {
            $errors[] = 'notion.target_page_id is required when Notion sync is enabled.';
        }

        return array('valid' => empty($errors), 'errors' => $errors, 'config' => $config);
    }

    public static function redact_secrets(array $config): array {
        unset($config['providers']['drafting']['api_key'], $config['notion']['token']);
        return $config;
    }

    private static function sanitize_sources(array $sources): array {
        $clean = array();
        $ids = array();
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $url = esc_url_raw((string) ($source['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $id = sanitize_key((string) ($source['id'] ?? sanitize_title((string) ($source['label'] ?? 'source'))));
            if ($id === '' || isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            $clean[] = array(
                'id'               => $id,
                'label'            => sanitize_text_field((string) ($source['label'] ?? $id)),
                'url'              => $url,
                'enabled'          => !empty($source['enabled']),
                'max'              => max(1, min(50, (int) ($source['max'] ?? 10))),
                'priority'         => max(1, min(100, (int) ($source['priority'] ?? 50))),
                'trust_level'      => in_array(($source['trust_level'] ?? 'standard'), array('high', 'standard', 'lead_only'), true) ? $source['trust_level'] : 'standard',
                'allowed_sections' => array_values(array_filter(array_map('sanitize_text_field', (array) ($source['allowed_sections'] ?? array())))),
                'image_policy'     => in_array(($source['image_policy'] ?? 'allow'), array('allow', 'block', 'review'), true) ? $source['image_policy'] : 'allow',
            );
        }
        return $clean;
    }
}
```

- [ ] **Step 4: Load the class in the test bootstrap and run tests**

Add:

```php
require_once dirname(__DIR__) . '/includes/class-lunara-journal-config-schema.php';
```

Run:

```bash
vendor/bin/phpunit tests/test-config-schema.php
```

Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-config-schema.php \
        plugins/lunara-journal-foundation/tests/test-config-schema.php \
        plugins/lunara-journal-foundation/tests/bootstrap.php
git commit -m "feat: define canonical journal configuration schema"
```

---

### Task 3: Add immutable configuration storage and lifecycle

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-config-repository.php`
- Create: `plugins/lunara-journal-foundation/tests/test-config-repository.php`
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php`

**Interfaces:**
- Consumes: `Lunara_Journal_Config_Schema`.
- Produces: `register_post_type()`, `create_initial_draft(array, int): int|WP_Error`, `save_draft(int, array, int): int|WP_Error`, `activate(int, int): array|WP_Error`, `rollback(int, int): array|WP_Error`, `get_active(): ?array`, `get_active_id(): int`, `list_versions(): array`.

- [ ] **Step 1: Write repository lifecycle tests**

The tests must verify that active configuration is immutable, activation swaps the active pointer atomically, and rollback creates a new version rather than reactivating the historical row.

```php
<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class Test_Lunara_Journal_Config_Repository extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_active_configuration_cannot_be_saved_in_place(): void {
        Functions\when('get_post_meta')->justReturn('active');
        $result = Lunara_Journal_Config_Repository::save_draft(41, Lunara_Journal_Config_Schema::defaults(), 1);
        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('lunara_config_immutable', $result->get_error_code());
    }

    public function test_next_patch_version_increments_patch(): void {
        self::assertSame('1.0.1', Lunara_Journal_Config_Repository::next_patch_version('1.0.0'));
        self::assertSame('2.4.10', Lunara_Journal_Config_Repository::next_patch_version('2.4.9'));
    }
}
```

- [ ] **Step 2: Run the repository test and verify failure**

Run:

```bash
vendor/bin/phpunit tests/test-config-repository.php
```

Expected: FAIL because `Lunara_Journal_Config_Repository` is not defined.

- [ ] **Step 3: Implement the private configuration CPT and lifecycle**

Use these constants and storage rules:

```php
final class Lunara_Journal_Config_Repository {
    public const POST_TYPE = 'lunara_journal_config';
    public const OPTION_ACTIVE_ID = 'lunara_journal_active_config_id';
    public const OPTION_ENFORCED = 'lunara_journal_control_plane_enforced';
    public const LOCK_KEY = 'lunara_journal_config_activation_lock';
    public const META_STATE = '_lunara_config_state';
    public const META_VERSION = '_lunara_config_version';
    public const META_HASH = '_lunara_config_hash';
    public const META_ACTIVATED_AT = '_lunara_config_activated_at';
    public const META_ACTIVATED_BY = '_lunara_config_activated_by';
    public const META_CHANGE_SUMMARY = '_lunara_config_change_summary';
    public const META_ROLLBACK_OF = '_lunara_config_rollback_of';
}
```

Register the post type with:

```php
register_post_type(self::POST_TYPE, array(
    'label'               => 'Journal Configurations',
    'public'              => false,
    'show_ui'             => false,
    'show_in_rest'        => false,
    'supports'            => array('title', 'editor', 'author', 'revisions'),
    'capability_type'     => 'post',
    'map_meta_cap'        => true,
    'exclude_from_search' => true,
));
```

Store sanitized JSON in `post_content`; use `wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)` and `hash('sha256', wp_json_encode($config))` for `META_HASH`.

`activate()` must:

1. require `current_user_can('manage_options')`;
2. reject non-draft/tested versions;
3. obtain `LOCK_KEY` with `set_transient(..., 1, 30)`;
4. validate the candidate;
5. set the previous active row to `superseded`;
6. set the candidate to `active`;
7. update `OPTION_ACTIVE_ID` and `OPTION_ENFORCED`;
8. emit `do_action('lunara_journal_configuration_activated', $candidate_id, $previous_id, $config)`;
9. always delete the lock in `finally`.

`rollback()` must clone the historical sanitized configuration, assign `next_patch_version()` based on the current active version, set `META_ROLLBACK_OF`, save as a draft, and pass the clone through `activate()`.

- [ ] **Step 4: Add repository includes and hooks to the Foundation bootstrap**

At the top-level plugin file, load the new classes before `Lunara_Journal_Foundation::bootstrap()`:

```php
require_once __DIR__ . '/includes/class-lunara-journal-protocol.php';
require_once __DIR__ . '/includes/class-lunara-journal-config-schema.php';
require_once __DIR__ . '/includes/class-lunara-journal-config-repository.php';
```

Inside `bootstrap()` add:

```php
add_action('init', array('Lunara_Journal_Config_Repository', 'register_post_type'), 4);
```

- [ ] **Step 5: Run repository and schema tests**

Run:

```bash
vendor/bin/phpunit tests/test-config-repository.php tests/test-config-schema.php
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-config-repository.php \
        plugins/lunara-journal-foundation/tests/test-config-repository.php \
        plugins/lunara-journal-foundation/lunara-journal-foundation.php
git commit -m "feat: add immutable journal configuration lifecycle"
```

---

### Task 4: Compile Dispatch and ChatGPT instructions from the canonical specification

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-prompt-compiler.php`
- Create: `plugins/lunara-journal-foundation/tests/test-prompt-compiler.php`

**Interfaces:**
- Consumes: sanitized canonical configuration.
- Produces: `compile_system(array): string`, `compile_user_directive(array): string`, `compile_chatgpt_summary(array): array`, `fingerprint(array): string`.

- [ ] **Step 1: Write compiler tests**

```php
<?php
use PHPUnit\Framework\TestCase;

final class Test_Lunara_Journal_Prompt_Compiler extends TestCase {
    public function test_compiler_emits_required_formatting_rules(): void {
        $config = Lunara_Journal_Config_Schema::defaults();
        $prompt = Lunara_Journal_Prompt_Compiler::compile_system($config);
        self::assertStringContainsString('Never use <h2>.', $prompt);
        self::assertStringContainsString('Start every entry with an original <h3> headline.', $prompt);
        self::assertStringContainsString('Film titles must use <em>.', $prompt);
        self::assertStringContainsString('Prefer 1 or 2 strong entries and never exceed 3.', $prompt);
    }

    public function test_compiler_fingerprint_is_stable(): void {
        $config = Lunara_Journal_Config_Schema::defaults();
        self::assertSame(
            Lunara_Journal_Prompt_Compiler::fingerprint($config),
            Lunara_Journal_Prompt_Compiler::fingerprint($config)
        );
    }
}
```

- [ ] **Step 2: Run tests and verify failure**

Run:

```bash
vendor/bin/phpunit tests/test-prompt-compiler.php
```

Expected: FAIL because the compiler does not exist.

- [ ] **Step 3: Implement deterministic prompt compilation**

The compiler must concatenate named sections in this exact order:

1. identity and purpose;
2. selection gate;
3. voice and judgment;
4. source-risk policy;
5. formatting;
6. structure;
7. banned phrases;
8. skip marker.

Use this public implementation shape:

```php
final class Lunara_Journal_Prompt_Compiler {
    public static function compile_system(array $config): string {
        $config = Lunara_Journal_Config_Schema::sanitize($config);
        $editorial = $config['editorial'];
        $banned = array_map(static function ($phrase) {
            return '"' . $phrase . '"';
        }, $editorial['banned_phrases']);

        $parts = array(
            "You are the LUNARA Journal editorial engine.\n" . $editorial['purpose'],
            "SELECTION\nPrefer 1 or 2 strong entries and never exceed " . (int) $config['automation']['maximum_entries'] . ". Skip thin, generic, promotional, or quota-filling items. Combine related items only when the grouping creates a real argument.",
            "VOICE\nPut reader interest first and critic judgment second. Use concrete nouns, active verbs, tight paragraphs, and earned humor. State institutional, racial, labor, taste, or business pressure plainly when the supplied evidence earns it. Mark inference as inference.",
            "SOURCE RISK\nWorld of Reel is a lead only: do not reuse its images, mimic its headline or structure, or create an entry without independent LUNARA judgment and natural attribution.",
            "FORMATTING\nReturn valid HTML only. Separate entries with <hr>. Never use <h2>. Start every entry with an original <h3> headline. Film titles must use <em>. Do not use lists, inline CSS, classes, divs, or <strong> on names.",
            "STRUCTURE\nEach entry must contain 2 to 4 real paragraphs and at least " . (int) $config['validation']['minimum_words'] . " words. The angle must be clear by paragraph two. End with a claim, implication, warning, or joke with teeth.",
            "BANNED LANGUAGE\nDo not use: " . implode(', ', $banned) . ".",
            "SKIP GATE\nIf nothing earns a reader's time, output exactly: <!-- LUNARA_SKIP: no reader-worthy items -->",
        );

        return implode("\n\n", $parts);
    }

    public static function compile_user_directive(array $config): string {
        $config = Lunara_Journal_Config_Schema::sanitize($config);
        return "Analyze the supplied film-news items using configuration "
            . $config['configuration_version']
            . ". Return only the strongest standalone LUNARA Journal entries in the required HTML format. Input News Data:\n";
    }

    public static function compile_chatgpt_summary(array $config): array {
        $config = Lunara_Journal_Config_Schema::sanitize($config);
        return array(
            'configuration_version' => $config['configuration_version'],
            'purpose'               => $config['editorial']['purpose'],
            'selection'             => $config['editorial']['selection'],
            'formatting'            => $config['editorial']['formatting'],
            'banned_phrases'        => $config['editorial']['banned_phrases'],
            'validation'            => $config['validation'],
            'workflow'              => $config['workflow'],
        );
    }

    public static function fingerprint(array $config): string {
        return hash('sha256', wp_json_encode(Lunara_Journal_Config_Schema::sanitize($config)));
    }
}
```

- [ ] **Step 4: Run compiler tests**

Run:

```bash
vendor/bin/phpunit tests/test-prompt-compiler.php
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-prompt-compiler.php \
        plugins/lunara-journal-foundation/tests/test-prompt-compiler.php
git commit -m "feat: compile journal prompts from canonical configuration"
```

---

### Task 5: Expose a stable internal Control Plane service

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane.php`
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-health.php`
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php`
- Modify: `plugins/lunara-journal-foundation/tests/bootstrap.php`

**Interfaces:**
- Consumes: repository, schema, compiler, protocol.
- Produces: `Lunara_Journal_Control_Plane::get_active_runtime_snapshot(): array|WP_Error`, `get_active_editorial_specification(): array|WP_Error`, `get_active_version(): string`, `is_enforced(): bool`, `health(): array`.

- [ ] **Step 1: Implement the service facade**

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Lunara_Journal_Control_Plane {
    public static function is_enforced(): bool {
        return get_option(Lunara_Journal_Config_Repository::OPTION_ENFORCED, '0') === '1';
    }

    public static function get_active_runtime_snapshot() {
        $active = Lunara_Journal_Config_Repository::get_active();
        if (!$active) {
            return new WP_Error('lunara_no_active_config', 'No active LUNARA Journal configuration exists.');
        }

        $config = Lunara_Journal_Config_Schema::sanitize($active['config']);
        return array(
            'protocol_version'      => Lunara_Journal_Protocol::VERSION,
            'schema_version'        => Lunara_Journal_Protocol::SCHEMA_VERSION,
            'configuration_id'      => (int) $active['id'],
            'configuration_version' => $config['configuration_version'],
            'configuration_hash'    => Lunara_Journal_Prompt_Compiler::fingerprint($config),
            'config'                => $config,
            'compiled'              => array(
                'system_prompt'  => Lunara_Journal_Prompt_Compiler::compile_system($config),
                'user_directive' => Lunara_Journal_Prompt_Compiler::compile_user_directive($config),
            ),
        );
    }

    public static function get_active_editorial_specification() {
        $snapshot = self::get_active_runtime_snapshot();
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }
        return Lunara_Journal_Prompt_Compiler::compile_chatgpt_summary($snapshot['config']);
    }

    public static function get_active_version(): string {
        $snapshot = self::get_active_runtime_snapshot();
        return is_wp_error($snapshot) ? '' : $snapshot['configuration_version'];
    }

    public static function health(): array {
        return Lunara_Journal_Health::snapshot();
    }
}
```

- [ ] **Step 2: Implement the health snapshot**

```php
final class Lunara_Journal_Health {
    public static function snapshot(): array {
        $dispatch_version = defined('LUNARA_DISPATCH_VERSION') ? LUNARA_DISPATCH_VERSION : '';
        $active = Lunara_Journal_Config_Repository::get_active();
        return array(
            'ok'                           => (bool) $active,
            'foundation_version'           => Lunara_Journal_Foundation::VERSION,
            'dispatch_version'             => $dispatch_version,
            'protocol_version'             => Lunara_Journal_Protocol::VERSION,
            'schema_version'               => Lunara_Journal_Protocol::SCHEMA_VERSION,
            'dispatch_protocol_compatible' => class_exists('Lunara_Dispatch_Control_Plane_Client')
                ? Lunara_Dispatch_Control_Plane_Client::supports_protocol(Lunara_Journal_Protocol::VERSION)
                : false,
            'active_configuration_id'      => $active ? (int) $active['id'] : 0,
            'active_configuration_version' => $active ? (string) $active['config']['configuration_version'] : '',
            'control_plane_enforced'       => Lunara_Journal_Control_Plane::is_enforced(),
            'acf_available'                => function_exists('acf_add_local_field_group'),
            'bridge_enabled'               => get_option(Lunara_Journal_Foundation::OPTION_ENABLED, '1') === '1',
            'notion_sync_enabled'          => $active ? !empty($active['config']['notion']['enabled']) : false,
        );
    }
}
```

- [ ] **Step 3: Load the service and health files from the plugin bootstrap**

Add exact `require_once` statements after repository/compiler loading and before `Lunara_Journal_Foundation::bootstrap()`.

- [ ] **Step 4: Add a smoke test for no-active-config behavior**

Stub `get_option()` to return `0` and the repository to return no active row; assert `get_active_runtime_snapshot()` returns `WP_Error` with code `lunara_no_active_config`.

- [ ] **Step 5: Run all Foundation tests**

Run:

```bash
vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane.php \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-health.php \
        plugins/lunara-journal-foundation/lunara-journal-foundation.php \
        plugins/lunara-journal-foundation/tests
git commit -m "feat: expose journal control plane runtime service"
```

---

### Task 6: Migrate current Dispatch settings into configuration version 1.0.0

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-migration.php`
- Create: `plugins/lunara-journal-foundation/tests/test-migration.php`
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php`

**Interfaces:**
- Consumes: existing options from Dispatch and Foundation.
- Produces: `inventory(): array`, `create_initial_configuration(int): int|WP_Error`, `legacy_snapshot(): array`.

- [ ] **Step 1: Write migration tests for the exact current option map**

Assert that migration reads these options without copying secret values into the canonical document:

```text
lunara_dispatch_enabled
lunara_dispatch_post_type
lunara_dispatch_post_status
lunara_dispatch_schedule
lunara_dispatch_provider
lunara_dispatch_max_tokens
lunara_dispatch_claude_model
lunara_dispatch_openai_model
lunara_dispatch_gemini_model
lunara_dispatch_grok_model
lunara_dispatch_voice_refinement
lunara_dispatch_system_prompt_override
lunara_dispatch_sources
lunara_dispatch_claude_key
lunara_dispatch_openai_key
lunara_dispatch_gemini_key
lunara_dispatch_grok_key
lunara_journal_bridge_access_profiles
lunara_journal_bridge_enabled
lunara_journal_dispatch_auto_convert
lunara_journal_dispatch_convert_mode
```

The test must confirm that key options become boolean presence flags and credential references only.

- [ ] **Step 2: Implement the inventory snapshot**

Store the snapshot in option `lunara_journal_control_plane_legacy_snapshot` with this redacted shape:

```php
array(
    'captured_at_gmt' => gmdate('c'),
    'dispatch' => array(
        'enabled'      => (bool) get_option('lunara_dispatch_enabled', 0),
        'post_type'    => (string) get_option('lunara_dispatch_post_type', 'journal'),
        'post_status'  => (string) get_option('lunara_dispatch_post_status', 'draft'),
        'schedule'     => (string) get_option('lunara_dispatch_schedule', 'daily'),
        'provider'     => (string) get_option('lunara_dispatch_provider', 'openai'),
        'max_tokens'   => (int) get_option('lunara_dispatch_max_tokens', 4096),
        'models'       => array(
            'claude' => (string) get_option('lunara_dispatch_claude_model', 'claude-opus-4-5'),
            'openai' => (string) get_option('lunara_dispatch_openai_model', 'gpt-4o'),
            'gemini' => (string) get_option('lunara_dispatch_gemini_model', 'gemini-2.5-pro'),
            'grok'   => (string) get_option('lunara_dispatch_grok_model', 'grok-4'),
        ),
        'credential_presence' => array(
            'claude' => trim((string) get_option('lunara_dispatch_claude_key', '')) !== '',
            'openai' => trim((string) get_option('lunara_dispatch_openai_key', '')) !== '',
            'gemini' => trim((string) get_option('lunara_dispatch_gemini_key', '')) !== '',
            'grok'   => trim((string) get_option('lunara_dispatch_grok_key', '')) !== '',
        ),
        'voice_refinement' => (string) get_option('lunara_dispatch_voice_refinement', ''),
        'prompt_override_hash' => hash('sha256', (string) get_option('lunara_dispatch_system_prompt_override', '')),
        'sources' => (array) get_option('lunara_dispatch_sources', array()),
    ),
    'foundation' => array(
        'bridge_enabled' => get_option('lunara_journal_bridge_enabled', '1') === '1',
        'auto_convert'   => get_option('lunara_journal_dispatch_auto_convert', '1') === '1',
        'convert_mode'   => (string) get_option('lunara_journal_dispatch_convert_mode', 'standard'),
        'access_profile_ids' => array_keys((array) get_option('lunara_journal_bridge_access_profiles', array())),
    ),
);
```

- [ ] **Step 3: Implement initial configuration creation**

`create_initial_configuration()` must:

1. abort if any configuration row already exists;
2. call `inventory()` and save the redacted snapshot;
3. start from schema defaults;
4. import enabled, schedule, provider, selected provider model, max tokens, and normalized sources;
5. force `post_type = journal` and `post_status = draft` regardless of legacy values;
6. import the current voice refinement as an editorial note field named `editorial.current_voice_refinement`;
7. store only the selected provider option name in `credential_ref`;
8. create version `1.0.0` in `draft` state;
9. never activate automatically.

- [ ] **Step 4: Add an administrator-only migration handler**

Register `admin_post_lunara_journal_create_initial_config` and enforce `manage_options` plus nonce `lunara_journal_create_initial_config`.

- [ ] **Step 5: Run migration tests**

Run:

```bash
vendor/bin/phpunit tests/test-migration.php
```

Expected: all migration tests pass and no secret literal appears in the generated configuration JSON.

- [ ] **Step 6: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-migration.php \
        plugins/lunara-journal-foundation/tests/test-migration.php \
        plugins/lunara-journal-foundation/lunara-journal-foundation.php
git commit -m "feat: migrate dispatch settings into canonical draft config"
```

---

### Task 7: Build the unified WordPress Control Plane interface

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-admin.php`
- Create: `plugins/lunara-journal-foundation/assets/admin/control-plane.css`
- Create: `plugins/lunara-journal-foundation/assets/admin/control-plane.js`
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php`

**Interfaces:**
- Consumes: repository, schema, compiler, migration, tester, health, Notion sync.
- Produces: `Journal → Control Plane` with tabs Dashboard, Editorial Specification, Automation, AI Providers, Sources, Workflow & Validation, Access & Audit, Notion.

- [ ] **Step 1: Register one submenu and remove the separate Foundation bridge submenu**

Use:

```php
add_submenu_page(
    'edit.php?post_type=journal',
    'LUNARA Journal Control Plane',
    'Control Plane',
    'manage_options',
    'lunara-journal-control-plane',
    array(__CLASS__, 'render')
);
```

Keep the old route as a redirect to:

```php
admin_url('edit.php?post_type=journal&page=lunara-journal-control-plane&tab=access')
```

- [ ] **Step 2: Render the tab navigation**

Use these exact tab keys and labels:

```php
private const TABS = array(
    'dashboard'  => 'Dashboard',
    'editorial'  => 'Editorial Specification',
    'automation' => 'Automation',
    'providers'  => 'AI Providers',
    'sources'    => 'Sources',
    'workflow'   => 'Workflow & Validation',
    'access'     => 'Access & Audit',
    'notion'     => 'Notion Mirror',
);
```

- [ ] **Step 3: Add administrator-only handlers**

Register these `admin_post_` actions with separate nonces:

```text
lunara_journal_save_config_draft
lunara_journal_test_config
lunara_journal_activate_config
lunara_journal_rollback_config
lunara_journal_notion_resync
lunara_journal_rotate_access_key
lunara_journal_run_dispatch
```

Every handler must call:

```php
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to manage the Journal Control Plane.', 403);
}
```

- [ ] **Step 4: Build the Dashboard panel**

The dashboard must show:

- Foundation version;
- Dispatch version;
- protocol compatibility;
- active configuration version and hash;
- draft configuration version;
- bridge identity health;
- last Dispatch run and next scheduled run;
- Notion last sync state;
- recent validation failures;
- primary actions: Test Draft, Activate Draft, Run Dispatch, Resync Notion.

Activation and rollback buttons must use confirmation text naming the exact version.

- [ ] **Step 5: Build the Editorial panel**

Provide structured fields for purpose, selection limits, voice flags, structure requirements, formatting flags, banned phrases, source-risk rules, and current voice refinement. Render the compiled prompt in a read-only `<textarea>` generated from the unsaved submitted configuration using the compiler.

- [ ] **Step 6: Build Automation, Providers, Sources, Workflow, Access, and Notion panels**

Apply these UI rules:

- `post_type` and `post_status` are displayed as locked values `journal` and `draft`.
- provider API-key inputs remain separate settings and display only `Configured` or `Not configured`.
- sources use repeatable rows with id, label, URL, enabled, max, priority, trust level, section allowlist, and image policy.
- workflow transitions are visible but fixed in release 1.0.
- access profiles retain the current scoped key generation and revocation behavior.
- Notion shows enabled, target page ID, token configured status, last sync version/time/error, and Resync.

- [ ] **Step 7: Add scoped CSS and JavaScript**

Load assets only when `$_GET['page'] === 'lunara-journal-control-plane'`. Use WordPress admin colors plus the existing LUNARA navy/gold accents. JavaScript handles tabs, repeatable sources, compiled-prompt preview requests, and confirmation dialogs; it must not activate configuration without a normal nonce-protected POST.

- [ ] **Step 8: Verify manually in wp-admin**

Expected:

- only one Journal settings entry is visible;
- old Foundation bridge URL redirects to Access & Audit;
- old Dispatch settings URL is unchanged until Task 11;
- active versions cannot be edited;
- draft versions can be saved and tested;
- activation requires an explicit administrator POST.

- [ ] **Step 9: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-admin.php \
        plugins/lunara-journal-foundation/assets/admin \
        plugins/lunara-journal-foundation/lunara-journal-foundation.php
git commit -m "feat: add unified journal control plane admin"
```

---

### Task 8: Add configuration testing without creating posts

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-tester.php`
- Modify: `plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-admin.php`

**Interfaces:**
- Consumes: schema, compiler, provider option references, source list, Dispatch parser when available.
- Produces: `test(array): array` with `valid`, `checks`, `errors`, `warnings`, `compiled_prompt_hash`.

- [ ] **Step 1: Implement deterministic checks**

Run these checks in order:

```text
schema
prompt_compile
provider_model
provider_key_presence
source_urls
protocol_compatibility
post_type_registered
acf_available
dispatch_parser_available
```

A source check uses `wp_safe_remote_get()` with timeout 12 seconds, redirection 3, user agent `LUNARA-Journal-Control-Plane/1.0`, and accepts HTTP 200-399. It records per-source failure without modifying seen-source state.

- [ ] **Step 2: Add an optional provider connectivity check**

The tester may make a minimal provider request only after an administrator clicks Test Configuration. It must use no feed data, create no post, and request the literal response `LUNARA_PROVIDER_OK`. A missing key is a blocking error; a remote provider outage is a blocking test error but never changes the current active configuration.

- [ ] **Step 3: Store test results on the draft configuration**

Store JSON under meta `_lunara_config_test_result`, timestamp under `_lunara_config_tested_at`, and hash under `_lunara_config_tested_hash`. Activation must require the tested hash to equal the current configuration hash.

- [ ] **Step 4: Verify activation refuses untested or changed drafts**

Expected error codes:

```text
lunara_config_not_tested
lunara_config_changed_since_test
lunara_config_test_failed
```

- [ ] **Step 5: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-tester.php \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-admin.php \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-config-repository.php
git commit -m "feat: require dry-run testing before config activation"
```

---

### Task 9: Add the direct PHP Control Plane client to Dispatch

**Files:**
- Create: `plugins/lunara-dispatch/includes/class-control-plane-client.php`
- Create: `plugins/lunara-dispatch/tests/test-control-plane-client.php`
- Modify: `plugins/lunara-dispatch/lunara-dispatch.php`
- Modify: `plugins/lunara-dispatch/includes/class-plugin.php:13-45`

**Interfaces:**
- Consumes: `Lunara_Journal_Control_Plane::get_active_runtime_snapshot()`.
- Produces: `supports_protocol(string): bool`, `is_available(): bool`, `is_enforced(): bool`, `runtime(): array|WP_Error`, `legacy_runtime(): array`.

- [ ] **Step 1: Write client tests**

```php
<?php
use PHPUnit\Framework\TestCase;

final class Test_Lunara_Dispatch_Control_Plane_Client extends TestCase {
    public function test_supported_protocol_is_major_one(): void {
        self::assertTrue(Lunara_Dispatch_Control_Plane_Client::supports_protocol('1.0.0'));
        self::assertTrue(Lunara_Dispatch_Control_Plane_Client::supports_protocol('1.8.3'));
        self::assertFalse(Lunara_Dispatch_Control_Plane_Client::supports_protocol('2.0.0'));
    }
}
```

- [ ] **Step 2: Implement the client**

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Lunara_Dispatch_Control_Plane_Client {
    public const SUPPORTED_PROTOCOL = '1.0.0';

    public static function supports_protocol(string $version): bool {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches)) {
            return false;
        }
        return (int) $matches[1] === 1;
    }

    public function is_available(): bool {
        return class_exists('Lunara_Journal_Control_Plane')
            && method_exists('Lunara_Journal_Control_Plane', 'get_active_runtime_snapshot');
    }

    public function is_enforced(): bool {
        return $this->is_available() && Lunara_Journal_Control_Plane::is_enforced();
    }

    public function runtime() {
        if (!$this->is_available()) {
            return $this->is_enforced()
                ? new WP_Error('lunara_control_plane_unavailable', 'The Journal Control Plane is enforced but unavailable.')
                : $this->legacy_runtime();
        }

        $snapshot = Lunara_Journal_Control_Plane::get_active_runtime_snapshot();
        if (is_wp_error($snapshot)) {
            return $this->is_enforced() ? $snapshot : $this->legacy_runtime();
        }
        if (!self::supports_protocol((string) $snapshot['protocol_version'])) {
            return new WP_Error('lunara_control_plane_protocol_mismatch', 'Dispatch is not compatible with the active Journal Control Plane protocol.');
        }
        return $snapshot;
    }

    public function legacy_runtime(): array {
        $provider = sanitize_key((string) get_option('lunara_dispatch_provider', 'openai'));
        return array(
            'protocol_version'      => '',
            'schema_version'        => '',
            'configuration_id'      => 0,
            'configuration_version' => 'legacy',
            'configuration_hash'    => '',
            'config' => array(
                'automation' => array(
                    'enabled'         => (bool) get_option('lunara_dispatch_enabled', 0),
                    'schedule'        => (string) get_option('lunara_dispatch_schedule', 'daily'),
                    'post_type'       => 'journal',
                    'post_status'     => 'draft',
                    'maximum_entries' => 3,
                    'retry_count'     => 1,
                ),
                'providers' => array(
                    'drafting' => array(
                        'provider'       => $provider,
                        'model'          => (string) get_option('lunara_dispatch_' . $provider . '_model', ''),
                        'max_tokens'     => (int) get_option('lunara_dispatch_max_tokens', 4096),
                        'credential_ref' => 'lunara_dispatch_' . $provider . '_key',
                    ),
                ),
                'sources' => Lunara_Dispatch_Sources::legacy_all(),
            ),
            'compiled' => array(
                'system_prompt'  => Lunara_Dispatch_Prompts::legacy_system_prompt(),
                'user_directive' => Lunara_Dispatch_Prompts::legacy_user_directive_prompt(),
            ),
        );
    }
}
```

- [ ] **Step 3: Load the client and expose it on the plugin orchestrator**

Add before `class-plugin.php`:

```php
require_once LUNARA_DISPATCH_DIR . 'includes/class-control-plane-client.php';
```

Add property and constructor initialization:

```php
/** @var Lunara_Dispatch_Control_Plane_Client */ public $control_plane;
$this->control_plane = new Lunara_Dispatch_Control_Plane_Client();
```

- [ ] **Step 4: Run client tests**

Run:

```bash
cd plugins/lunara-dispatch
composer install
vendor/bin/phpunit tests/test-control-plane-client.php
```

Expected: protocol tests pass.

- [ ] **Step 5: Commit**

```bash
git add plugins/lunara-dispatch/includes/class-control-plane-client.php \
        plugins/lunara-dispatch/tests/test-control-plane-client.php \
        plugins/lunara-dispatch/lunara-dispatch.php \
        plugins/lunara-dispatch/includes/class-plugin.php
git commit -m "feat: connect dispatch to journal control plane"
```

---

### Task 10: Route Dispatch prompts, providers, schedules, and sources through the active configuration

**Files:**
- Modify: `plugins/lunara-dispatch/includes/class-plugin.php:48-127`
- Modify: `plugins/lunara-dispatch/includes/class-ai-client.php:32-58`
- Modify: `plugins/lunara-dispatch/includes/class-prompts.php:19-245`
- Modify: `plugins/lunara-dispatch/includes/class-sources.php:43-114`
- Create: `plugins/lunara-dispatch/tests/test-runtime-routing.php`

**Interfaces:**
- Consumes: Control Plane runtime snapshot.
- Produces: a single runtime source for generation behavior after enforcement.

- [ ] **Step 1: Add legacy-named methods before changing call sites**

Rename the current prompt methods without changing their bodies:

```php
voice_refinement_note()       -> legacy_voice_refinement_note()
system_prompt_override()      -> legacy_system_prompt_override()
default_system_prompt()       -> legacy_default_system_prompt()
system_prompt()               -> legacy_system_prompt()
user_directive_prompt()       -> legacy_user_directive_prompt()
user_directive($news_data)    -> legacy_user_directive($news_data)
```

Add new runtime methods:

```php
public static function system_prompt(array $runtime): string {
    return (string) ($runtime['compiled']['system_prompt'] ?? self::legacy_system_prompt());
}

public static function user_directive_prompt(array $runtime): string {
    return (string) ($runtime['compiled']['user_directive'] ?? self::legacy_user_directive_prompt());
}

public static function user_directive(array $runtime, string $news_data): string {
    return self::user_directive_prompt($runtime) . "\n" . $news_data;
}
```

- [ ] **Step 2: Change AI generation to accept the runtime snapshot**

Replace the signature and option lookups:

```php
public function generate($news_data, array $runtime) {
    $drafting = $runtime['config']['providers']['drafting'];
    $provider = sanitize_key((string) $drafting['provider']);
    $system = Lunara_Dispatch_Prompts::system_prompt($runtime);
    $user_prompt = Lunara_Dispatch_Prompts::user_directive_prompt($runtime);
    $user = Lunara_Dispatch_Prompts::user_directive($runtime, $news_data);
    $tokens = max(1024, min(16000, (int) $drafting['max_tokens']));
    $model = sanitize_text_field((string) $drafting['model']);
    $credential_ref = sanitize_key((string) $drafting['credential_ref']);

    switch ($provider) {
        case 'openai':
            return $this->call_openai($system, $user, $tokens, $model, $credential_ref);
        case 'gemini':
            return $this->call_gemini($system, $user, $tokens, $model, $credential_ref);
        case 'grok':
            return $this->call_grok($system, $user, $tokens, $model, $credential_ref);
        case 'claude':
            return $this->call_claude($system, $user_prompt, $news_data, $tokens, $model, $credential_ref);
        default:
            return new WP_Error('invalid_provider', 'The active Journal configuration selected an unsupported AI provider.');
    }
}
```

Each provider method reads its key with `get_option($credential_ref, '')`; it must never accept a secret in the runtime array.

- [ ] **Step 3: Route sources through the active runtime**

Preserve the old option implementation as `legacy_all()` and add:

```php
public static function all(array $runtime = array()): array {
    if (!empty($runtime['config']['sources']) && is_array($runtime['config']['sources'])) {
        return array_values($runtime['config']['sources']);
    }
    return self::legacy_all();
}

public static function enabled(array $runtime = array()): array {
    return array_values(array_filter(self::all($runtime), static function ($source) {
        return !empty($source['enabled']);
    }));
}
```

Modify the feed fetcher so `fetch_all(array $runtime)` calls `Lunara_Dispatch_Sources::enabled($runtime)`.

- [ ] **Step 4: Route scheduling through active configuration**

On configuration activation, Foundation emits `lunara_journal_configuration_activated`. Dispatch subscribes and reschedules its cron using `config['automation']['schedule']`. Keep the old `update_option_lunara_dispatch_schedule` listener only before enforcement.

- [ ] **Step 5: Add runtime-routing tests**

Verify that an enforced active runtime overrides legacy provider, model, schedule, prompt, and sources; verify that secret values are still read from option references.

- [ ] **Step 6: Run Dispatch tests**

Run:

```bash
vendor/bin/phpunit
```

Expected: all Dispatch tests pass.

- [ ] **Step 7: Commit**

```bash
git add plugins/lunara-dispatch/includes/class-plugin.php \
        plugins/lunara-dispatch/includes/class-ai-client.php \
        plugins/lunara-dispatch/includes/class-prompts.php \
        plugins/lunara-dispatch/includes/class-sources.php \
        plugins/lunara-dispatch/includes/class-feed-fetcher.php \
        plugins/lunara-dispatch/tests/test-runtime-routing.php
git commit -m "feat: route dispatch runtime through control plane"
```

---

### Task 11: Make the old Dispatch settings page read-only after activation

**Files:**
- Modify: `plugins/lunara-dispatch/includes/class-admin.php:32-350`

**Interfaces:**
- Consumes: `Lunara_Dispatch_Control_Plane_Client::is_enforced()`.
- Produces: a single visible configuration surface at `Journal → Control Plane`.

- [ ] **Step 1: Move the legacy settings link under Journal during pre-activation only**

Before enforcement, keep the existing settings page functional. After enforcement, `settings_page()` must render:

```php
<div class="wrap">
    <h1>LUNARA Dispatch Automation</h1>
    <div class="notice notice-info inline">
        <p><strong>Managed by Journal Control Plane.</strong> Editorial rules, automation, providers, sources, workflow, and access are now controlled from one authoritative screen.</p>
    </div>
    <p><a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=journal&page=lunara-journal-control-plane')); ?>">Open Journal Control Plane</a></p>
    <?php $this->render_visual_assignment_queue(); ?>
</div>
```

- [ ] **Step 2: Disable legacy save handlers after enforcement**

`register_settings()` and `handle_sources_post()` must return immediately when the Control Plane is enforced. Manual Run Now remains available only through the Control Plane handler.

- [ ] **Step 3: Preserve diagnostics and the visual assignment queue**

Do not remove run reports, image diagnostics, reset-seen behavior, migration history, or the visual queue. Link to those diagnostics from the Control Plane Dashboard and Access & Audit tabs.

- [ ] **Step 4: Verify settings are non-authoritative**

Change a legacy option directly in the database and confirm an enforced Dispatch run still uses the active configuration snapshot.

- [ ] **Step 5: Commit**

```bash
git add plugins/lunara-dispatch/includes/class-admin.php
git commit -m "feat: retire duplicate dispatch settings after activation"
```

---

### Task 12: Enforce draft-only creation and attach complete provenance

**Files:**
- Create: `plugins/lunara-dispatch/includes/class-run-context.php`
- Modify: `plugins/lunara-dispatch/includes/class-plugin.php:127-345`
- Modify: `plugins/lunara-dispatch/includes/class-post-builder.php:210-313`
- Create: `plugins/lunara-dispatch/tests/test-draft-only.php`
- Create: `plugins/lunara-dispatch/tests/test-provenance.php`
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-provenance.php`

**Interfaces:**
- Consumes: runtime snapshot, fetched source items, provider/model, plugin versions.
- Produces: `Lunara_Dispatch_Run_Context::from_runtime(array, array): array`, `Lunara_Journal_Provenance::attach(int, array): void`.

- [ ] **Step 1: Write draft-only tests**

Assert that `get_target_post_status()` always returns `draft`, even when the legacy option is `publish`, `pending`, or `private` and even when malformed runtime input requests another status.

- [ ] **Step 2: Implement the run context**

```php
final class Lunara_Dispatch_Run_Context {
    public static function from_runtime(array $runtime, array $items): array {
        $drafting = $runtime['config']['providers']['drafting'];
        return array(
            'run_id'                => wp_generate_uuid4(),
            'generated_at_gmt'      => gmdate('c'),
            'configuration_id'      => (int) $runtime['configuration_id'],
            'configuration_version' => (string) $runtime['configuration_version'],
            'configuration_hash'    => (string) $runtime['configuration_hash'],
            'provider'              => sanitize_key((string) $drafting['provider']),
            'model'                 => sanitize_text_field((string) $drafting['model']),
            'dispatch_version'      => LUNARA_DISPATCH_VERSION,
            'foundation_version'    => defined('LUNARA_JOURNAL_FOUNDATION_VERSION') ? LUNARA_JOURNAL_FOUNDATION_VERSION : '',
            'source_items'          => array_values(array_map(static function ($item) {
                return array(
                    'source' => sanitize_text_field((string) ($item['source_label'] ?? '')),
                    'title'  => sanitize_text_field((string) ($item['title'] ?? '')),
                    'url'    => esc_url_raw((string) ($item['url'] ?? '')),
                );
            }, $items)),
        );
    }
}
```

- [ ] **Step 3: Force draft status in Dispatch**

Replace `get_target_post_status()` with:

```php
public function get_target_post_status() {
    return 'draft';
}
```

Pass `'draft'` literally into `split_into_individual_posts()`.

- [ ] **Step 4: Extend the post-builder signature**

```php
public function split_into_individual_posts(
    $html,
    array $section_image_map,
    $post_type,
    $post_status = 'draft',
    array $run_context = array()
) {
    $post_type = 'journal';
    $post_status = 'draft';
```

After successful insertion, call:

```php
if (class_exists('Lunara_Journal_Provenance')) {
    Lunara_Journal_Provenance::attach((int) $new_id, $run_context);
}
```

- [ ] **Step 5: Implement Foundation provenance attachment**

Write post meta and ACF fields for:

```text
_lunara_run_id
_lunara_configuration_id
_lunara_configuration_version
_lunara_configuration_hash
_lunara_initial_provider
_lunara_initial_model
_lunara_dispatch_version
_lunara_foundation_version
_lunara_generated_at_gmt
_lunara_source_items
journal_writer_source = Dispatch
journal_dispatch_actor = Lunara Dispatch Automation
journal_status = needs_chatgpt_review
journal_original_dispatch_copy = original generated body
```

Also disable Jetpack publicize/newsletter metadata exactly as the current bridge already does.

- [ ] **Step 6: Run tests**

Run both plugin suites. Expected: draft-only and provenance tests pass.

- [ ] **Step 7: Commit**

```bash
git add plugins/lunara-dispatch/includes/class-run-context.php \
        plugins/lunara-dispatch/includes/class-plugin.php \
        plugins/lunara-dispatch/includes/class-post-builder.php \
        plugins/lunara-dispatch/tests/test-draft-only.php \
        plugins/lunara-dispatch/tests/test-provenance.php \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-provenance.php
git commit -m "feat: enforce draft creation and record journal provenance"
```

---

### Task 13: Centralize workflow transitions and deterministic validation

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-workflow.php`
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-validator.php`
- Create: `plugins/lunara-journal-foundation/tests/test-workflow.php`
- Create: `plugins/lunara-journal-foundation/tests/test-validator.php`
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php:1254-1368`

**Interfaces:**
- Produces: `can_transition(string, string, string): bool`, `transition(int, string, array): true|WP_Error`, `validate_post(int, array): array`.

- [ ] **Step 1: Define the exact transition graph**

```php
private const TRANSITIONS = array(
    'collected'            => array('drafted', 'rejected'),
    'drafted'              => array('needs_chatgpt_review', 'rejected'),
    'needs_chatgpt_review' => array('validation_failed', 'ready_for_dalton', 'rejected'),
    'validation_failed'    => array('needs_chatgpt_review', 'ready_for_dalton', 'rejected'),
    'ready_for_dalton'     => array('needs_chatgpt_review', 'published', 'rejected'),
    'published'            => array(),
    'rejected'             => array('needs_chatgpt_review'),
);
```

ChatGPT actor type may transition only to `validation_failed`, `ready_for_dalton`, or back to `needs_chatgpt_review`. Only a logged-in administrator may transition to `published`.

- [ ] **Step 2: Move validation logic out of the monolith**

Validate:

- Journal post type;
- draft status for AI-mediated workflow;
- nonempty title;
- minimum word count;
- minimum paragraph count;
- no `<h2>`;
- no inline style/class/div/list markup;
- source URL present;
- featured image present;
- excerpt present;
- SEO description present;
- banned phrases absent;
- configuration version present;
- valid workflow state.

Return:

```php
array(
    'valid' => empty($errors),
    'errors' => $errors,
    'warnings' => $warnings,
    'checked_at_gmt' => gmdate('c'),
    'configuration_version' => $configuration_version,
)
```

- [ ] **Step 3: Update REST validate and mark-ready callbacks**

`rest_validate_draft()` calls the validator, persists the report, and transitions to `validation_failed` when invalid. `rest_mark_ready()` refuses invalid drafts and transitions to `ready_for_dalton` when valid. WordPress post status remains `draft`.

- [ ] **Step 4: Record manual publication**

Hook `transition_post_status`. When a Journal post changes from a non-publish status to `publish`, require a logged-in user with `publish_posts`, record user ID/name/time and the active configuration version, then transition workflow to `published`.

- [ ] **Step 5: Run tests**

Expected: invalid transitions fail, ChatGPT cannot publish, valid drafts can become ready, and manual publication records a human actor.

- [ ] **Step 6: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-workflow.php \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-validator.php \
        plugins/lunara-journal-foundation/tests/test-workflow.php \
        plugins/lunara-journal-foundation/tests/test-validator.php \
        plugins/lunara-journal-foundation/lunara-journal-foundation.php
git commit -m "feat: centralize journal workflow and validation"
```

---

### Task 14: Extend the ChatGPT bridge with live configuration retrieval and strict permissions

**Files:**
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php:879-1217`
- Modify: `plugins/lunara-journal-foundation/openapi/lunara-journal-bridge.openapi.json`
- Create: `plugins/lunara-journal-foundation/tests/test-rest-permissions.php`

**Interfaces:**
- Adds read-only endpoints: `/journal/configuration/active`, `/journal/configuration/specification`, `/journal/configuration/version`.
- Preserves draft endpoints and scoped access profiles.

- [ ] **Step 1: Register configuration read endpoints**

```text
GET /wp-json/lunara/v1/journal/configuration/active
GET /wp-json/lunara/v1/journal/configuration/specification
GET /wp-json/lunara/v1/journal/configuration/version
```

All require the existing `schema` scope. Responses use `Lunara_Journal_Config_Schema::redact_secrets()` and never include compiled provider credentials.

- [ ] **Step 2: Explicitly deny configuration mutations**

Do not register REST routes for save, test, activate, rollback, source mutation, schedule mutation, provider mutation, or Notion settings. Add permission tests that POST/PUT/PATCH/DELETE against likely mutation paths return 404 or 405.

- [ ] **Step 3: Require configuration retrieval before draft update**

`rest_update_draft()` accepts required body field `configuration_version`. It rejects a stale or absent version with HTTP 409 and code `lunara_configuration_version_mismatch`.

- [ ] **Step 4: Update the OpenAPI schema**

Add operation IDs:

```text
getActiveJournalConfiguration
getJournalEditorialSpecification
getActiveJournalConfigurationVersion
```

Require `configuration_version` for update, validate, and mark-ready requests. Keep custom-header auth `X-Lunara-Bridge-Token`.

- [ ] **Step 5: Run REST permission tests**

Expected: ChatGPT can read active specification and operate on eligible drafts, but cannot activate, publish, delete, mutate sources, or mutate schedules.

- [ ] **Step 6: Commit**

```bash
git add plugins/lunara-journal-foundation/lunara-journal-foundation.php \
        plugins/lunara-journal-foundation/openapi/lunara-journal-bridge.openapi.json \
        plugins/lunara-journal-foundation/tests/test-rest-permissions.php
git commit -m "feat: expose live config to chatgpt without activation authority"
```

---

### Task 15: Implement one-way Notion synchronization

**Files:**
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-notion-client.php`
- Create: `plugins/lunara-journal-foundation/includes/class-lunara-journal-notion-sync.php`
- Create: `plugins/lunara-journal-foundation/tests/test-notion-payload.php`
- Modify: `plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-admin.php`
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php`

**Interfaces:**
- Consumes: active redacted configuration and health snapshot.
- Produces: `build_payload(array, array): array`, `queue(int): void`, `sync_version(int): array|WP_Error`, `retry_failed(): void`.

- [ ] **Step 1: Store Notion credentials separately**

Use options:

```text
lunara_journal_notion_token
lunara_journal_notion_target_page_id
lunara_journal_notion_last_sync
lunara_journal_notion_last_error
lunara_journal_notion_retry_attempt
```

The token field is write-only in admin and never prefilled.

- [ ] **Step 2: Write payload tests**

Assert that the payload contains active version, activation time, structured editorial specification, compiled prompt summary, automation, provider/model names, source inventory, workflow, validation, health, change summary, and WordPress Control Plane URL. Assert that provider keys, bridge tokens, and Notion token strings are absent.

- [ ] **Step 3: Implement the Notion client**

Use WordPress HTTP API with:

```php
array(
    'timeout' => 30,
    'headers' => array(
        'Authorization'  => 'Bearer ' . $token,
        'Content-Type'   => 'application/json',
        'Notion-Version' => '2026-03-11',
    ),
)
```

Update the page with `PATCH https://api.notion.com/v1/pages/{page_id}` and `erase_content => true`, then append no more than 100 first-level blocks per `PATCH https://api.notion.com/v1/blocks/{page_id}/children` request. Split longer payloads into batches of 100.

- [ ] **Step 4: Build idempotent content blocks**

The page begins with:

```text
LUNARA Journal HQ
Active configuration: {version}
Activated: {timestamp}
Source of truth: WordPress Journal Control Plane
```

Then render headings for Editorial Specification, Automation, Providers, Sources, Workflow, Validation, Health, and Change History. Include a callout stating that Notion is read-only and cannot activate production configuration.

- [ ] **Step 5: Queue sync after activation**

On `lunara_journal_configuration_activated`, schedule single event `lunara_journal_notion_sync_version` five seconds later. Activation returns success without waiting for Notion.

- [ ] **Step 6: Add retry behavior**

Retry after 5 minutes, 30 minutes, and 2 hours. After the third failure, stop automatic retries, store the error, and show an admin notice. Manual Resync resets the retry count.

- [ ] **Step 7: Run payload tests and HTTP-mock integration tests**

Expected: redaction passes, 429 and 5xx responses queue retry, and failed sync never changes active configuration or Dispatch state.

- [ ] **Step 8: Commit**

```bash
git add plugins/lunara-journal-foundation/includes/class-lunara-journal-notion-client.php \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-notion-sync.php \
        plugins/lunara-journal-foundation/tests/test-notion-payload.php \
        plugins/lunara-journal-foundation/includes/class-lunara-journal-control-plane-admin.php \
        plugins/lunara-journal-foundation/lunara-journal-foundation.php
git commit -m "feat: add one-way notion mirror for active journal config"
```

---

### Task 16: Version, package, and run full acceptance testing

**Files:**
- Modify: `plugins/lunara-journal-foundation/lunara-journal-foundation.php:1-35`
- Modify: `plugins/lunara-journal-foundation/README.md`
- Modify: `plugins/lunara-dispatch/lunara-dispatch.php:1-35`
- Modify: `plugins/lunara-dispatch/README.md`
- Create: `docs/operations/lunara-journal-control-plane-runbook.md`

**Interfaces:**
- Produces: deployable Foundation `1.0.0` ZIP, Dispatch `3.1.0` ZIP, rollback packages, and an operator runbook.

- [ ] **Step 1: Set release versions**

Foundation:

```php
Version: 1.0.0
const VERSION = '1.0.0';
define('LUNARA_JOURNAL_FOUNDATION_VERSION', '1.0.0');
```

Dispatch:

```php
Version: 3.1.0
define('LUNARA_DISPATCH_VERSION', '3.1.0');
```

- [ ] **Step 2: Run static verification**

Run:

```bash
find plugins/lunara-journal-foundation -name '*.php' -print0 | xargs -0 -n1 php -l
find plugins/lunara-dispatch -name '*.php' -print0 | xargs -0 -n1 php -l
cd plugins/lunara-journal-foundation && vendor/bin/phpunit
cd ../lunara-dispatch && vendor/bin/phpunit
```

Expected: no PHP syntax errors and all tests pass.

- [ ] **Step 3: Create production backups before deployment**

Archive the currently active plugin folders as:

```text
lunara-journal-foundation-0.3.0-rollback.zip
lunara-dispatch-3.0.15-rollback.zip
```

Export the redacted legacy inventory and record current cron events. Do not include API keys in exported files.

- [ ] **Step 4: Deploy Foundation first**

Upload Foundation `1.0.0`, activate, and verify:

- Journal CPT and `/journal/` page still work;
- existing Journal entries are unchanged;
- bridge identity and health pass;
- no active configuration exists until administrator migration.

- [ ] **Step 5: Deploy Dispatch second**

Upload Dispatch `3.1.0`. Before Control Plane activation, confirm legacy behavior still runs in draft-only mode and no duplicate settings disappear unexpectedly.

- [ ] **Step 6: Create and test configuration 1.0.0**

From `Journal → Control Plane`:

1. create initial draft from legacy inventory;
2. inspect imported sources/provider/model/schedule;
3. confirm post type `journal` and status `draft` are locked;
4. run Test Configuration;
5. compare compiled prompt against the prior effective prompt;
6. confirm no post was created.

- [ ] **Step 7: Activate explicitly and verify authority transfer**

Activate `1.0.0` as administrator. Confirm:

- old Dispatch settings page says Managed by Journal Control Plane;
- Dispatch reads active version `1.0.0`;
- Control Plane health is green;
- Notion sync is queued but cannot block activation.

- [ ] **Step 8: Run the controlled end-to-end acceptance cycle**

1. temporarily set source limits so the run creates at most one draft;
2. click Run Dispatch;
3. confirm one `journal` draft with complete provenance and `needs_chatgpt_review`;
4. open the private GPT;
5. retrieve active configuration version;
6. retrieve the draft without modifying it;
7. approve one revision and save with matching configuration version;
8. validate deterministically;
9. mark ready for Dalton;
10. confirm WordPress post status remains `draft`;
11. review audit and provenance in WordPress;
12. publish manually as Dalton;
13. confirm human publisher attribution;
14. confirm Notion mirror matches active version and contains no secret values.

- [ ] **Step 9: Test rollback semantics**

Create and test a harmless `1.0.1` change, activate it, then roll back to the content of `1.0.0`. Confirm the system creates a new active version `1.0.2` with `rollback_of = 1.0.0`; it must not reactivate or alter the original `1.0.0` record.

- [ ] **Step 10: Package release ZIPs**

Run:

```bash
cd plugins
zip -qr ../lunara-journal-foundation-1.0.0.zip lunara-journal-foundation \
  -x '*/vendor/*' '*/tests/*' '*/composer.lock' '*/phpunit.xml.dist'
zip -qr ../lunara-dispatch-3.1.0.zip lunara-dispatch \
  -x '*/vendor/*' '*/tests/*' '*/composer.lock' '*/phpunit.xml.dist'
sha256sum ../lunara-journal-foundation-1.0.0.zip ../lunara-dispatch-3.1.0.zip
```

- [ ] **Step 11: Write the solo-operator runbook**

The runbook must answer:

- where each setting lives;
- how to draft/test/activate/roll back configuration;
- how to run Dispatch;
- how to review through ChatGPT;
- how to resync Notion;
- how to rotate keys;
- how to diagnose provider/feed/Notion failures;
- how to restore either rollback ZIP;
- what must never be pasted into chat.

- [ ] **Step 12: Commit**

```bash
git add plugins/lunara-journal-foundation \
        plugins/lunara-dispatch \
        docs/operations/lunara-journal-control-plane-runbook.md
git commit -m "release: journal control plane 1.0"
```

---

## Verification Matrix

| Requirement | Verification |
|---|---|
| One authoritative configuration | Change a legacy Dispatch option after activation; runtime remains unchanged. |
| Manual publication only | Dispatch and ChatGPT attempts cannot create `publish`, `future`, `pending`, or `private` posts. |
| ChatGPT live configuration | Draft update without matching active version returns HTTP 409. |
| External activation prohibited | No REST route or Notion path can activate or roll back configuration. |
| Version immutability | Editing an active config returns `lunara_config_immutable`. |
| Rollback preserves history | Rollback creates a new semantic version with `rollback_of`. |
| Notion non-blocking | Simulated Notion 503 leaves configuration active and Dispatch operational. |
| Secret redaction | Test scans configuration JSON, REST payloads, audit output, and Notion blocks for known secret fixtures. |
| Provenance | Generated draft identifies run ID, config version/hash, provider/model, plugin versions, source items, and actors. |
| Existing Journal safety | Pre/post database comparison shows unchanged existing post IDs, content, terms, and media IDs. |

## Final Self-Review Results

- Every design requirement maps to at least one task.
- The plan contains no unresolved placeholder fields.
- Public interfaces use consistent names across Foundation and Dispatch.
- The active configuration is immutable and administrator-activated only.
- ChatGPT and Notion remain read/edit clients without activation authority.
- Dispatch remains a separate generation engine and is not rebuilt.
- The first release avoids two-way Notion editing, autonomous ChatGPT editing, and automatic publication.
