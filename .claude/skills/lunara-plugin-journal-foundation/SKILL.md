```markdown
# lunara-plugin-journal-foundation Development Patterns

> Auto-generated skill from repository analysis

## Overview
This skill teaches the core development patterns and conventions used in the `lunara-plugin-journal-foundation` repository. The codebase is written in TypeScript, follows strong file and code organization standards, and uses a test-driven approach (test framework undetected). This guide covers file naming, import/export styles, commit patterns, and how to structure and run tests.

## Coding Conventions

### File Naming
- Use **kebab-case** for all file and directory names.
  - Example:  
    ```
    journal-entry.ts
    utils/helpers.ts
    ```

### Import Style
- Use **relative imports** for referencing modules within the codebase.
  - Example:
    ```typescript
    import { formatDate } from './utils/format-date';
    ```

### Export Style
- Use **named exports** for all modules.
  - Example:
    ```typescript
    // In journal-entry.ts
    export function createJournalEntry(data: EntryData) { ... }
    ```

### Commit Patterns
- Commit messages are **freeform** but typically concise (average ~57 characters).
- No strict prefixing required, but clarity is valued.

## Workflows

### Adding a New Feature
**Trigger:** When implementing a new feature or module  
**Command:** `/add-feature`

1. Create a new file using kebab-case naming.
2. Implement feature logic using TypeScript.
3. Use named exports for all exported functions or constants.
4. Import dependencies using relative paths.
5. Write corresponding test(s) in a `.test.ts` file.
6. Commit changes with a clear, concise message.

### Fixing a Bug
**Trigger:** When resolving a bug or issue  
**Command:** `/fix-bug`

1. Locate the relevant file(s) using kebab-case naming.
2. Apply the bug fix, maintaining code style.
3. Update or add tests in the corresponding `.test.ts` file.
4. Commit with a descriptive message about the fix.

### Writing and Running Tests
**Trigger:** When verifying code correctness  
**Command:** `/run-tests`

1. Write test files alongside implementation files, using the pattern `*.test.ts`.
2. Use the (undetected) test framework's CLI to run all tests.
3. Ensure all tests pass before merging changes.

## Testing Patterns

- Test files follow the `*.test.ts` naming convention.
  - Example:
    ```
    journal-entry.test.ts
    ```
- Place test files close to the code they test.
- Each test file should use named exports if exporting helpers.
- Test framework is unknown; use the project's standard test runner.

## Commands
| Command      | Purpose                                 |
|--------------|-----------------------------------------|
| /add-feature | Scaffold and implement a new feature    |
| /fix-bug     | Guide for fixing bugs and updating tests|
| /run-tests   | Run all test suites                     |
```
