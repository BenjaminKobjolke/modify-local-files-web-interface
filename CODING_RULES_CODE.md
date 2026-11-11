<!-- Managed by /coding-rules:apply — do not edit rule blocks by hand -->
<!-- Step file for code runs, generated from CODING_RULES.md — CODING_RULES.md stays the complete rule set -->

# Version
19

Increase this version number whenever this rule file changes.

# Common Rules (All Languages)

These rules apply to all projects, regardless of language. Language-specific rules live in the
corresponding `*_RULES.md` files.

---

## Keep CODING_RULES.md in Sync
<!-- steps: code -->

When working on a project, copy all relevant rules into the project's `CODING_RULES.md` file
(project root). The project's `CLAUDE.md` carries only a small versioned pointer block that
mandates reading `CODING_RULES.md` before code work — never the full rules, and never an
`@import` of `CODING_RULES.md` (imports auto-expand into context every turn).

- Always include all rules from `COMMON_RULES.md` and `IMPLEMENTATION_FLOW.md`.
  `COMMON_RULES.md` is copied into the project's `CODING_RULES.md`;
  `IMPLEMENTATION_FLOW.md` is copied into the project's own `IMPLEMENTATION_FLOW.md`
- `apply` also writes `CODING_RULES_PLAN.md`, `CODING_RULES_REVIEW.md` and
  `CODING_RULES_CODE.md` from the same rule blocks. Use `<!-- steps: plan, review, code -->`
  under a heading to limit its section.
- Also include applicable language-specific, project-type, and supplemental rule files
  (see `PROJECT_TYPES.md` for the project-type overview)
- Include optional addon rule files only when the user has opted in to that addon
- If applicability is unclear, ask the user which rules to include
- Include each source file's `# Version` block with its copied rules
- If `CODING_RULES.md` already exists, compare each source file's version with the corresponding
  copied rule block and update only stale or unversioned blocks, keeping the result deduplicated
- If `CLAUDE.md` contains inlined rule blocks or coding-rule `@import` lines from an earlier run,
  migrate that content into `CODING_RULES.md` and leave only the pointer block in `CLAUDE.md`

---

## Use Objects for Related Values

When multiple related values must be passed between classes or methods, bundle them into a
dedicated object (e.g., DTO/Settings/Config) instead of passing many parameters. This improves
readability, reduces call-site churn, and makes changes safer.

---

## No Bag-of-Keys Returns at Module Boundaries

When a public method on a manager/repository/service returns data that crosses a module
boundary, the return type must be a typed object (DTO, value object, or domain model) — never
a raw associative array indexed by string keys. Plain `array` returns silently swallow shape
bugs: a missing key reads as `null`, a list-vs-single mix-up reads as "no data", and renames
go undetected by static analysis.

- **Anti-pattern.** `getSettingsValue(...)` returns `array|null`; callers do `$result['value']`,
  `$result['type']`. A consumer mis-indexes `$result[0]['value']` after a refactor; nothing
  flags the change. The function silently returns `null` and downstream defaults take over.
- **Correct pattern.** Return a class — `getSettingsElement(...): ?SettingsElement`. The class
  exposes `getValue()`, `getType()`, `exists()`. Typed, autocompleted, statically checked;
  renames propagate via the IDE.
- **Lists vs single must be obvious from the type and the name.** `getThing(): ?Thing`
  (zero or one) vs `getThings(): ThingList` or `iterable<Thing>`. Never overload the same
  return type to mean both.
- **Distinguish absent from empty.** `null` from a lookup means "not found"; an empty
  collection means "found, but had nothing". A typed return makes this contract explicit;
  a bag-of-keys array hides it.
- **JSON-decoded blobs are arrays too.** The rule applies equally to `json_decode($column, true)`
  results that cross a module boundary — wrap them in a value object before they leave the
  layer that owns the schema.
- **Internal helpers may stay arrays.** This rule targets *public* API on managers and the
  boundary where a domain abstraction starts. Pure-private array juggling inside a single
  method is fine.

---

## Reuse Existing Models Before Inventing Array Shapes

Before designing a new return type or DTO, search the codebase for an existing domain class
that already owns the same data. Most "should this be a DTO?" decisions are actually
"is there already a `Contest` / `User` / `Order` class that should absorb this method?"

- Grep for the table name, the primary key, and the most distinctive column.
- If a model already exists with a constructor that accepts the row shape, use it — don't
  invent a parallel array shape that mirrors the same columns.
- Adding a `getXxxObject()` alongside a legacy `getXxxData()` is acceptable as a migration
  step; keep both only until consumers are migrated, then delete the array-returning version.

---

## Tests Pin the Shape Before the Refactor

When converting a bag-of-keys return to a typed object, write a **characterization test
first** that locks the current behavior using the existing API, run it green against the
unrefactored code, and then refactor. The same test (or a renamed-but-equivalent one) must
remain green afterward.

This converts "I think the new object preserves behavior" into "the test proves it." Pair
with the "Test-Driven Development" rule below — characterization tests are TDD applied to
refactors instead of new features.

---

## Test-Driven Development for Features and Bug Fixes

Follow TDD when implementing features or fixing bugs:

1. Write tests first
2. Run the tests and confirm they fail
3. Implement the change or fix
4. Run the tests again and confirm they pass

---

## Integration Tests

Every project must include integration tests in addition to unit tests. Integration tests verify that
components work correctly together and catch issues that unit tests alone cannot detect.

---

## Test Runner Scripts
<!-- steps: review, code -->

Every project must provide the following batch files in the `tools/` directory:

- `tools/run_tests.bat` — runs unit tests
- `tools/run_integration_tests.bat` — runs integration tests

These scripts ensure a consistent way to execute tests across environments.

Both scripts must show progress while tests run: report the current setup stage, suite, or test
before potentially slow work, and show results as execution proceeds. A start banner followed only
by a final summary is insufficient. Prefer the runner's verbose output and stream or flush it so
callers can see it during execution. Print one line per test: `flutter test --reporter expanded`,
`pytest -v`, `phpunit --debug` (`--testdox` lists the tests only after the run on PHPUnit 10+).
For a legitimate long wait without ordinary output, report the current stage or test and elapsed
time at least every 60 seconds. Elapsed-time updates must not imply that a test completed or
advanced. If output is captured for logs or filtering, keep progress visible during execution;
replaying it only after completion does not count.

Both must **exit with the test runner's own exit code** — capture it right after the run
(`set TESTRESULT=%ERRORLEVEL%`) and end with `exit /b %TESTRESULT%`. A bat that prints
"Some tests failed!" but exits 0 reports green to CI, to callers and to AI agents. Never pipe the
test command into a plain filter — the pipe's exit code is the *filter's*, not the runner's. Never
redirect the run into a temp file and print or filter that afterwards either: the script is then
silent for the whole run. Run the command directly so its output streams. If output has to be
filtered or logged, do it while it streams and return the runner's own code, e.g. `powershell
-NoProfile -Command "<test command> | Where-Object { $_ -notmatch '<noise>' }; exit $LASTEXITCODE"`,
or use the runner's own log-file option.

---

## No `pause` in Batch Files
<!-- steps: review, code -->

Batch files must never contain a `pause` line. `pause` waits for a keypress, so any bat that has one
hangs forever when run by CI, another bat, or an AI agent. End with an explicit `exit /b <code>`
instead, and let the caller decide whether to keep the window open (`cmd /k`).

---

## Prefer Type-Safe Values

Use strong, explicit types instead of loosely typed or stringly typed values (e.g., typed DTOs,
enums, generics, typed settings). This ensures mistakes are caught at compile time or by tests
early in development.

---

## String Constants

Centralize string constants in a dedicated module/class. Do not scatter raw strings across
the codebase. Use language-appropriate patterns for constants and reuse them consistently.

---

## Reusable Tooling

Before building project-specific infrastructure scripts (audits, codemods,
build helpers, lint checks, etc.) for a project, check the matching
language's `*_setup_files/` folder under this `coding-rules` repo for an
existing equivalent. If found, copy or reference it. If not:

1. Build the script in the project and prove it on real data.
2. Copy the script into the right `*_setup_files/tools/` folder.
3. Document it in that language's `*_RULES.md` so the next project picks
   it up automatically.

This keeps cross-project tooling consistent and prevents the same script
from being re-invented in every new project.

---

## README.md is Mandatory

Every project must have a `README.md` file in the root directory. It should include:

- Project name and description
- Installation/setup instructions
- Usage examples
- Dependencies and requirements

---

## Don't Repeat Yourself (DRY)

Avoid code duplication. If the same logic appears in multiple places, extract it into a
reusable function, class, module, or utility.

- Duplicate code is harder to maintain and leads to bugs
- Extract shared logic into helpers or base abstractions
- Use constants for repeated values

---

## Derive, Don't Duplicate — One Value Owns the Derivation

When one value strictly determines another, pass only the determinant and derive the rest —
never thread both side-by-side through call sites, constructors, and events. Two co-varying
parameters are a functional dependency in disguise; passing both lets them drift into illegal
combinations.

- **Anti-pattern.** `createLog(ActionCategory $cat, ActionType $type)` threaded through ~10
  sites. Nothing stops a caller passing `category: email, type: lead.created`.
- **Correct pattern.** The richer type owns the relationship: `ActionType::category()` returns
  its `ActionCategory` via a single exhaustive `match`. Call sites pass only `ActionType`;
  category is always derived, so a mismatch is unconstructable.
- **Apply when** one value *determines* the other (a true functional dependency).
- **Don't apply when** the relationship is many-to-many or genuinely independent — forcing a
  derivation that doesn't exist couples things that should stay separate.
- **Keep derivation cheap and pure** — a getter/match, no DB or IO behind a call that looks free.
- **Keep the mapping exhaustive** (enum + exhaustive match) so a new case cannot silently skip
  its derived value.

This is Single Source of Truth applied to parameters, and a form of "make illegal states
unrepresentable." Pairs with "Prefer Type-Safe Values" and "Self-Describing Classes".

---

## Keep It Simple (KISS)

Prefer the simplest solution that actually works. Complicated logic for a simple result must be
kept to a minimum — a future maintainer (or you, at 3am) has to understand it.

- **YAGNI.** Don't build for a need that isn't here yet: no interface with a single
  implementation, no factory for one product, no config for a value that never changes.
- **Boring over clever.** Clever is what someone decodes later. The obvious solution wins.
- **Deletion over addition.** The shortest working change is usually the right one.
- Pairs with "Don't Repeat Yourself" and "No God Classes" — simplicity is what those rules
  are protecting.

---

## Confirm Dependency Versions

When adding a new package or library, choose its version yourself from verified facts. Do not
ask the user to confirm a routine version choice.

- Never assume a version from memory: check the authoritative release source (the package
  registry or the project's official releases) for the latest stable release.
- Check the project's constraints: runtime/SDK version, the existing lock file, peer
  dependencies and the package's supported platforms.
- Use the latest stable release that satisfies them. Skip pre-releases (alpha, beta, rc, dev).
- Pin or lock the resolved version the way the project already does (lock file, exact pin, or
  its range convention), and name the chosen version and where it was checked in the plan or
  change summary.
- A version the user specified wins.
- Ask the user only when a real tradeoff remains: the latest stable needs a new major line or a
  runtime upgrade, supported versions conflict, a security advisory affects the candidate, or
  the release source cannot be checked.
- Avoid outdated packages that may have security vulnerabilities or missing features.

---

## Error Handling & Logging Strategy

Every project must have a centralized error handler rather than ad-hoc try/catch blocks scattered
throughout the codebase.

- Use structured logging (not `print`/`console.log`/`echo`)
- Log at appropriate levels: debug, info, warning, error
- Include context in log messages (module name, operation, relevant IDs)

---

## Centralized Logger — Single Off Switch

Route all logging through one dedicated logger class/module. Never call the language's
built-in output directly for logging (`print`, `console.log`, `echo`, `Debug.Log`,
`System.out`). Code calls the project logger; the project logger wraps the underlying sink.

- **One toggle.** Because every log goes through one place, logging can be turned off,
  level-filtered, or redirected (file, console, remote) from a single config flag — without
  touching call sites. Example: a `logEnabled` / `logLevel` setting the logger checks once.
- **Levels live in the logger.** Callers pass a level (debug/info/warning/error); the logger
  decides what is emitted based on central config. Callers never branch on "should I log?".
- **Wrap, don't scatter.** Built-in calls (`print`, framework loggers, `Debug.Log`) appear
  in exactly one file — the logger implementation. Everywhere else imports the logger.
- **Language specifics** still apply (e.g. Unity `[Conditional]` stripping, Flutter `logger`
  package, Python `logging`) — but they are configured inside the central logger, not at
  call sites.

The logger's name is fixed per language so it is the same known type in every project (see each
`*_RULES.md` for details):

| Language      | Class / export     | File                |
|---------------|--------------------|---------------------|
| Python        | `AppLogger`        | `app_logger.py`     |
| PHP           | `Logger`           | `Logger.php`        |
| Dart/Flutter  | `AppLogger`        | `app_logger.dart`   |
| Kotlin        | `AppLogger`        | `AppLogger.kt`      |
| C# (plain)    | `AppLogger`        | `AppLogger.cs`      |
| C++           | `AppLogger`        | `AppLogger.h` / `AppLogger.cpp` |
| Unity C#      | `GameLog` (static) | `GameLog.cs`        |
| Svelte/JS/TS  | `logger` (export)  | `logger.ts`         |
| Arduino       | `Log`              | `Log.h` / `Log.cpp` |
| Rust          | `log` / `tracing` facade, `app_logger::init()` | `app_logger.rs` |

---

## App Error Reporting (BINDING)

- **Applies to:** released end-user apps distributed to people other than their developer, including app stores, installers, downloads, and deployed web apps. Libraries, CLI tools, API-only repositories, and personal tools are exempt. Local logging alone does not satisfy this rule.
- **Capture:** unexpected, actionable failures at framework and process boundaries: unhandled framework or UI errors, uncaught async or platform errors, startup failures, and 5xx responses from the app's own API. Do not report expected failures such as validation errors, offline conditions, cancelled requests, or 4xx responses.
- **Report contents:** error kind, platform, app version, exception type, message, and stack trace when available. For HTTP failures, include only method, path, and status. Never include credentials, tokens, query strings, headers, request or response bodies, or personal data. Before sending, redact tokens, JWT-shaped strings, emails, and values associated with `password`, `token`, `secret`, or `key`; cap message and stack length.
- **Delivery:** send to the project's own API. Use a hosted error service only when the user approves it in the project plan. Use a dedicated transport that cannot report its own failures and is excluded from debug network logs. Set a bounded timeout, deduplicate within an app start, and cap reports per start. Document whether offline or failed delivery is dropped or placed in a bounded queue, and log the failure locally. Reporting must not change what the user sees. Debug builds pointed at production must not send reports.
- **Receiver:** validate input, redact and cap it again, store no IP address, request headers, or request body, rate-limit by client address, and apply a configured retention period. Provide a protected, paged, filterable read route so an authorised person or scheduled check can review reports. Without protected retrieval, the rule is not met.
- Document captured and excluded data, limits, offline behavior, and how to read reports in `docs/APP_ERROR_REPORTING.md`.
- Before the first release with reporting and after changing it, use a non-production build to send a synthetic report containing a fake token and email. Read it back through the protected route and confirm both values were redacted.
- Automatic triage or repair todos based on the read route are optional.
- Platform how-to: `flutter/APP.md` → "Error Reporting". Server half: `project_type/REST_API.md` → "App Error Report Intake (BINDING)". The GoRabbits app and Turbo Habits API are the reference implementations.

---

## Input Validation at Boundaries

Always validate data at system boundaries — API inputs, user input, file uploads, external service
responses.

- Never trust external data; validate before processing
- Use language-appropriate validation libraries (e.g., Pydantic, Zod, FluentValidation)
- Fail fast with clear error messages when validation fails

---

## API Clients: the API Sorts, Filters and Pages (BINDING)

Applies to every project that reads data from an API — app, SPA, desktop tool, script.

- **Show what the API returns, in the order it returns it.** Never download a whole collection
  to sort, filter, search, count, group or join it locally.
- **Read lists page by page.** Send the page size and position and load the next page when the
  user scrolls or asks for more. Never loop over all pages to rebuild the full list on start or
  on refresh.
- **A new order, filter or search is a new request** with that parameter, starting at the first
  page. Reordering the rows already held is wrong as soon as the list is paged: the client holds
  one page, not the list.
- **Do not download again what you already hold.** After a create, update or delete use the row
  the response returned, or re-request the visible page — not the whole collection.
- **A missing parameter is API work.** When an API you own lacks the sort, filter, search,
  paging or count a screen needs, request it from the API project (linked todo or
  `/feedback:write`) and plan against that. "The list is small or unpaged today, so the client
  can do it" is not a reason. For a third-party API that cannot change, use the paging and
  sorting it offers and mark any remaining local work with a comment naming the missing
  capability.

The server half is `project_type/REST_API.md` -> "List Endpoints: Paging, Sorting, Filtering".

---

## Shared Contract Repository for API and Clients (BINDING)

When two or more projects we own talk over the wire — an API plus an app, an API plus a desktop
app, or all three — keep their shared values in one contract repository. This does not apply to
third-party APIs.

- **Why:** each side keeps its own copy of every value that crosses the wire. When one side adds a
  value the other does not know, the other side can break (for example, the API added a todo type
  and the watcher rejected the whole todo list).
- **One separate repository** (`<system>-contract`) holds the contract: at minimum every shared
  enum in `enums.json`, shaped as `"<Name>": {"values": [sorted unique], "direction":
  "api_to_client" | "client_to_api", "description": "<where it appears>"}`, plus a `README.md`
  with the change rules and the list of consumers. Do not add version numbers; the submodule
  pointer is the version.
- **Every project includes it as a git submodule at `contract/`.** Pick up changes with
  `git submodule update --remote contract` and commit the pointer bump. Production code never
  reads `contract/`; it is test input only.
- **The API is the producer:** export the contract from its own constants (one map, one export
  script) and add a freshness test that fails when the committed file differs from the export.
- **Each client is a consumer:** test that every mapped enum matches the contract in its
  direction (`api_to_client` sets are equal so the client knows every value;
  `client_to_api` client values are a subset) and that every contract name is mapped.
- Both tests skip when `contract/` is not checked out.
- **Change order:** for an `api_to_client` value, update the contract, release clients that know
  it, then have the API emit it. For a `client_to_api` value, update the contract and API first,
  then have the client send it. To remove a value, stop it at the API, then remove it from the
  contract, then from clients. Never rename a value; renaming is an add plus a remove. Add a new
  shared enum to the contract in the same change that introduces it.
- Clients still tolerate an unknown value (skip the row; do not fail the whole response) as a
  last line of defence, not as permission to skip the order.
- **Setting it up** is work in each project: when one project of the system lacks the submodule
  or its test, request it there (linked todo or `/feedback:write`).

---

## Upstream Forks

In a fork that merges changes from an upstream project, these rules apply to new and changed code
only. Do not reformat, rename, move, or refactor upstream code that the task does not need. Prefer
new files for features, and keep project-specific setup in new files plus the `CLAUDE.md` pointer
so upstream merges stay clean.

---

## Coding-Rules Plugin Updates in Tickets Watcher Runs

A todo may need a rules version that is published in the coding-rules marketplace but not
installed on this machine yet (for example a new rule file). A Tickets Watcher run — a phase the
watcher started for a todo, working from `tickets/<id>/` — may install it itself. Any other
session asks the user to update the plugin instead.

- **Precondition check.** Read `claude plugin list --json` and take the entry
  `coding-rules@<marketplace>`. The check passes only when its `version` is at least the
  required version and `<installPath>/rules/<required rule file>` exists. Use `installPath`;
  never build the cache path by hand.
- **Bounded update.** Only when the check fails, run exactly these two commands, then check
  again:
  1. `claude plugin marketplace update <marketplace>` (the marketplace of the plugin id, today
     `claude-coding-rules`);
  2. `claude plugin update coding-rules@<marketplace> --scope user --json`.
  Nothing else: no `install`, `uninstall`, `enable` or `disable`, no other plugin or
  marketplace, no `-y` and no `--accept-command`.
- **Stop when it does not work.** When a command exits non-zero, asks to confirm a command, or
  the second check still fails, stop with `OUTCOME: blocked` and
  `BLOCKER: coding-rules <version> with <rule file> is not installed`, quoting the CLI output.
- **Never edit machine-managed plugin files.** No agent copies files into, or edits anything
  under, `~/.claude/plugins/` (cache, `installed_plugins.json`, marketplaces) — not in a watcher
  run either.
- **Published first.** The update installs only what the marketplace source holds. The rules
  todo that adds the version must be done (its `plugin.json` version bumped, and pushed when the
  source is a git remote). When the source lacks the version, the update cannot help: wait for
  that todo instead.
- **Next process.** The run that updated keeps the plugin it started with. Read the new rule
  files from `installPath`, or let the dependent work continue in a later watcher run.
- **Planning.** A plan run checks the installed version read-only with
  `claude plugin list --json` and records it in the plan's context. When it is too old, the
  plan's first ordered step is "check, update if needed, check again" as above, before any work
  that needs the rules. It is an ordered step the implement run does, not a by-hand step.
  Dependent work resumes only after that check passes.

---

## Maximum File Length — 300 Lines

Split files when they exceed 300 lines to keep code navigable during fast iteration.

- Extract classes, functions, or components into separate modules
- Group related extractions logically (by domain, not by type)
- Exceptions: generated files, configuration files, test files with many similar cases

---

## Naming Conventions

Be consistent within a project. Follow these defaults unless the language or framework dictates
otherwise:

- Files: `snake_case` (or language convention, e.g., `PascalCase` for C# classes)
- Classes: `PascalCase`
- Functions/methods: language convention (`snake_case` for Python/PHP, `camelCase` for Dart/JS/C#)
- Constants: `UPPER_SNAKE_CASE`
- Variables: language convention (`snake_case` for Python/PHP, `camelCase` for Dart/JS/C#)

---

## Comments Explain Why, Not What

Comment intent and non-obvious reasoning — not a restatement of the code. Good names carry the
*what*; comments carry the *why*.

- **Anti-pattern.** `i++ // increment i`. Redundant comments add noise and rot the moment the
  code changes.
- **Correct pattern.** Document *why* a workaround exists, why a non-obvious algorithm was
  chosen, or a constraint that isn't visible locally (`// API rejects batches > 500`).
- Prefer self-documenting code (clear names, small functions) over a comment that compensates
  for unclear code — see "Naming Conventions" and "Keep It Simple".
- Document the purpose of each module/class at its top.
- Keep comments in sync with the code; delete stale ones rather than letting them mislead.

---

## Security Baseline

Every project must follow these minimum security practices:

- Never commit secrets (`.env`, API keys, credentials, private keys)
- Escape output to prevent XSS/injection attacks
- Use parameterized queries or ORM-provided methods — never concatenate user input into queries
- Validate and sanitize all user input at system boundaries
- Keep dependencies updated to avoid known vulnerabilities

---

## No Hardcoded Environment Values

Never hardcode environment-specific values in code — filesystem paths, hostnames, IP addresses,
ports, base URLs. They differ across machines and environments and make code non-portable.

- **Anti-pattern.** `connect("192.168.1.50:5432")`, `open("C:\\Users\\bob\\data\\out.json")`.
- **Correct pattern.** Read them from the project's central config (the config class each
  `*_RULES.md` already mandates), with a committed `.example` template documenting every key.
- Distinct from the secrets rule above: this is about **portability** (runs anywhere), not
  secrecy. A non-secret hostname still belongs in config, not in code.

---

## Debug Tools Only on Local Machines (BINDING)

Debug switches and debug-only features are on only on a developer machine — never on a
deployed copy (live site, server, store or installer build).

- **Off by default.** Tracked config, `.example` templates and build defaults ship with debug
  off (`'debug' => false`, `DEBUG=0`, release build). A missing key reads as off.
- **On only through a local override** that is gitignored and excluded from deploy
  (`config/app.local.php`, `.env.local`, a debug build; listed in `.gitignore` and
  `.deployignore` or the upload filter). Commit a `.example` of the override with debug on
  and name the copy step in the README setup.
- **One switch gates everything debug-only:** raw error output and stack traces, preview or
  sample routes (`?preview=...`), debug toolbars, verbose network logs, seeded test logins.
  Never gate them on a URL parameter, a hidden route or a hostname check alone.
- **Deployed errors:** show a short generic message; send the detail to the logger (see
  "Centralized Logger").
- Found debug on in a tracked or deployed config? Turn it off there and move the local need
  into the override — "previews need it" is not a reason to keep it on.

---

## No God Classes

A class that handles too many responsibilities becomes fragile, hard to test, and impossible to
reuse. Keep each class focused on a single purpose.

- **Warning signs**: more than 5 public methods, more than 4 constructor dependencies, or methods that span unrelated domains (e.g., a class that validates input, queries the database, and sends emails)
- Split by responsibility: extract collaborators (e.g., a `Validator`, a `Repository`, a `Notifier`) rather than piling logic into one class
- If you struggle to name the class without using "Manager", "Handler", "Service", or "Helper" as a catch-all, it likely does too much
- This complements the 300-line file rule — a short class can still be a god class if it owns too many concerns

---

## Self-Describing Classes

When behavior depends on which fields or properties a class has — such as search, serialization,
display, validation, or auditing — the class itself must declare those fields through a contract
(interface, abstract method, attribute/annotation, or introspection pattern). Never hardcode field
lists in consuming code.

- **Anti-pattern**: A search service contains a hardcoded list of fields to index for each entity;
  adding a new field requires updating every consumer manually
- **Correct pattern**: Each class implements a contract (e.g., `GetSearchableFields()`,
  `GetDisplayColumns()`) that returns its own relevant fields, so adding a field in one place
  automatically propagates everywhere
- This applies to any cross-cutting concern that operates over class fields: search, filtering,
  export, form generation, diffing, logging, etc.
- Combine with compile-time checks where the language supports them (e.g., sealed interfaces,
  exhaustive matching) to ensure new fields cannot be silently ignored

---

## Search Across Partial Words

When implementing user-facing search, split the query on whitespace and match every term as a
case-insensitive substring of the searchable text, regardless of term order. For example,
`release bat` should find `release_build.bat`. Preserve the project's existing searchable fields.

---

## Inject Collaborators, Don't Fold Dependencies In

Composition reuse comes in two shapes, and they differ sharply in how much coupling they add to
the reusing class. **Folding** a helper into a class (mixin, trait, multiple inheritance, copy-in
include) merges *all of the helper's own dependencies* into that class — reuse five such helpers
and every one of their imports is now the host's coupling. **Injecting** a collaborator adds a
single dependency: the collaborator, which is built once and shared as a hub.

Prefer injected collaborators. Reserve fold-in reuse for helpers that are stateless and carry no
dependencies of their own.

- **Anti-pattern**: A controller reuses five behavior mixins/traits; each brings its own service,
  DTO, and constant imports, so the controller transitively depends on a few dozen things and is
  hard to test in isolation.
- **Correct pattern**: Extract that behavior into a collaborator object injected via the
  constructor. The controller depends on the collaborator; the collaborator is reused across many
  controllers as a shared, well-tested hub.

### Inject services; never instantiate one inside a method

Constructing a service with `new` (or the language equivalent) inside a method hides the
dependency from the class's public contract and makes it impossible to substitute in a test. Pass
collaborators in through the constructor.

- **Anti-pattern**: A method does `helper = new EmailPreparer(); helper.prepare(...)`. Nothing in
  the class signature reveals the dependency, and no test can replace it.
- **Correct pattern**: Inject `EmailPreparer` once; the method calls the injected instance.

### Collapse config-callback swarms into one value object

When a base class pulls its configuration from the subclass through many small overridable getters
that the subclass fills in one-line-each, each getter is a separate touch-point and the wiring is
spread across dozens of methods. Bundle the related values into a single config object built once
and handed to the base (see **Use Objects for Related Values**). This also keeps such classes off
the wrong side of **No God Classes**.

- **Anti-pattern**: A subclass implements `getSendEndpoint()`, `getSendSuccessKey()`,
  `getSendFailureRedirect()`, and a dozen more one-line getters, each naming one constant.
- **Correct pattern**: The subclass builds one `SendConfig` value object once; the base reads its
  fields.

# Version
5

Increase this version number whenever this rule file changes.

# PHP Rules

See `COMMON_RULES.md` for rules that apply to all languages.

## Optional rule files

- `php/APP.md` — PHP web apps and APIs: `.htaccess`, Twig, localization, Cycle ORM, config files, DB-mirror test bats, middleware. Skip it for libraries.
- `project_type/PHP_LIBRARY.md` — Composer packages with `"type": "library"`.
- Run `/coding-rules:apply` to add them.

## PHP Version

Use PHP 8.5 for all projects. Set the requirement in `composer.json`:

```json
{
    "require": {
        "php": "^8.5"
    }
}
```

---

## Code Quality
<!-- steps: review, code -->

### PHPStan

Use PHPStan at level 5 for static analysis:

```bash
composer require --dev phpstan/phpstan
```

```neon
# phpstan.neon
parameters:
    level: 5
    paths:
        - src
```

### PHP-CS-Fixer

Use PHP-CS-Fixer with PSR-12 rules for code style:

```bash
composer require --dev friendsofphp/php-cs-fixer
```

```php
// .php-cs-fixer.php
<?php

$finder = PhpCsFixer\Finder::create()->in(__DIR__ . '/src');

return (new PhpCsFixer\Config())
    ->setRules(['@PSR12' => true])
    ->setFinder($finder);
```

Run formatting: `php vendor/bin/php-cs-fixer fix`

---

## Logging

Route all logging through one class named **`Logger`** (`src/Logger.php`), a thin PSR-3 wrapper
(e.g. over Monolog). Feature code calls `Logger`, never `echo`/`error_log`/`var_dump` or a raw
Monolog instance — this gives a single enable/level toggle and one place to change the sink.

```php
$logger->info('User loaded', ['user_id' => $id]);
$logger->error('Failed to load user', ['user_id' => $id, 'exception' => $e]);
```

---

## Upgrading to a Newer PHP Version
<!-- steps: code -->

When migrating an existing PHP codebase to a newer language version, follow the workflow in [`PHP_UPGRADE_TO_NEWER_VERSION.md`](PHP_UPGRADE_TO_NEWER_VERSION.md). High-level steps:

1. **Inventory** — scan the codebase for deprecated patterns with PHPCompatibility, file the findings as a baseline.
2. **Automate fixes** — run Rector against the target `PhpVersion::PHP_XX` with focused deprecation rules.
3. **Manual cleanup** — patch what Rector can't (bundled libraries, removed extensions, magic-method renames).
4. **Verify** — green PHPUnit suite on the new PHP binary + PHPCompatibility scan reports zero project-code issues.
5. **Smoke test** — exercise UI flows in a browser before declaring the migration done.

Templated batch runners and a Rector config live in [`php_setup_files/`](php_setup_files/). Copy them into the project's `tools/` folder and fill in the gitignored `php_upgrade_config.bat`.

---

## Wrap DB Rows and JSON Columns in Value Objects

PHP-specific application of the common rule "No Bag-of-Keys Returns at Module Boundaries".
PHP makes the bag-of-keys trap easy because every `fetch_assoc_all()`, `json_decode($col, true)`,
and `$_POST` payload starts life as `array<string, mixed>`. Stop the array from leaking past
the boundary that owns its schema.

### Repository methods return objects, not rows

```php
// Bad — caller indexes 20 string keys, silent on column rename
public function getContestDataForAdmin(int $id): ?array { ... }

// Good
public function getContestForAdmin(int $id): ?Contest { ... }
```

The repository *may* internally hold an `array<string, mixed>`, but the public method returns
a constructed object. PHPStan level 5+ then catches consumer regressions automatically when
properties change.

### List vs single is in the name and the return type

```php
public function findById(int $id): ?Thing;
/** @return Thing[] */
public function findAllForContest(int $contestId): array;
```

Never let one method return "row OR list of rows depending on input". A function whose body
does `fetch_assoc_all()` and whose name reads as singular (`getXxxValue`, `getXxxElement`) is
the exact shape that produces silent-`null` bugs at the call site.

### JSON-encoded value columns get a value object

A row's `value` column that stores `{"text":"...","fields":[...]}` JSON belongs in a
`XxxValue` class with `getText(): string`, `getFields(): array`. Decoding inline at every
call site spreads the schema across the codebase; each consumer becomes a maintenance
hazard when the JSON shape changes.

### `isset($result['key'])` on a method return is a smell

If you find yourself writing `isset($result['some_key'])` on the output of a method you
control, that method should be returning an object whose presence is signalled by `?Type`
(nullable return) and whose fields are accessed by getters. The only legitimate use of
`isset` on associative-array keys is on raw I/O input (`$_POST`, decoded external JSON) at
the boundary, before validation produces a typed object.

### Migration recipe for array-returning methods with many consumers

1. Write a characterization test against the current method that exercises representative
   inputs and locks the current outputs.
2. Add a parallel `getXxxObject()` method returning the typed class.
3. Migrate consumers one file per commit; the test stays green.
4. Delete the array-returning method once `grep` finds zero callers.

PHPStan level 5+ catches most regressions automatically once the return type changes from
`array` to `?ClassName` — leverage this rather than relying on review.

---

## Self-Describing Classes

Implement the common "Self-Describing Classes" rule using interfaces or PHP 8 attributes.

### Option A: Interface with explicit method

```php
interface Searchable
{
    /** @return string[] */
    public function getSearchableFields(): array;
}

class Customer implements Searchable
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
    ) {}

    public function getSearchableFields(): array
    {
        return [$this->name, $this->email, $this->phone];
    }
}
```

### Option B: PHP 8 attribute on properties

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class Searchable {}

class Customer
{
    #[Searchable] public readonly string $name;
    #[Searchable] public readonly string $email;
    public readonly string $internalNotes; // not searchable
}

// Consumer reads attributes via ReflectionClass at bootstrap
```

Prefer the interface approach for straightforward cases. Use attributes when you need
fine-grained per-property control.

---

## Traits and Config Getters (Coupling)

See **Inject Collaborators, Don't Fold Dependencies In** in `COMMON_RULES.md` for the general
rule. In PHP the fold-in mechanism is the trait.

### `use SomeTrait` merges the trait's dependencies into the class

A `use SomeTrait` copies the trait's body — and every class it imports — into the host. Stacking
several behavior traits on one class quietly makes all of their dependencies the host's own. Prefer
a constructor-injected collaborator service for anything that carries dependencies.

```php
// Anti-pattern: five behavior traits, each pulling its own services/DTOs/constants into the class
class InvoiceDocumentController extends AbstractController
{
    use BulkUnlinkTrait;
    use DocumentDownloadTrait;
    use DocumentPreviewTrait;
    use DocumentSendTrait;
    use DocumentEmailPreviewTrait;
}

// Correct: inject one collaborator; its dependencies stay its own
class InvoiceDocumentController extends AbstractController
{
    public function __construct(private DocumentActions $documents) {}
}
```

### Replace config-getter swarms with a value object

When a trait/base declares many `abstract protected function getXxx(): string` hooks that each
subclass fills one-line-each, the constant references pile up across a dozen tiny methods. Bundle
them into one config value object (see **Use Objects for Related Values**).

```php
// Anti-pattern
protected function getSendEndpoint(): string      { return ApiEndpoints::INVOICE_SEND; }
protected function getSendSuccessKey(): string     { return TranslationKeys::INVOICE_SEND_SUCCESS; }
protected function getSendFailureRedirect(): string { return UrlPaths::INVOICES; }
// ...several more

// Correct
protected function documentSendConfig(): DocumentSendConfig
{
    return new DocumentSendConfig(
        sendEndpoint: ApiEndpoints::INVOICE_SEND,
        successKey:   TranslationKeys::INVOICE_SEND_SUCCESS,
        redirect:     UrlPaths::INVOICES,
    );
}
```

### Never `new` a service inside a method — inject it

```php
// Anti-pattern: hidden dependency, untestable
public function send(...): ResponseInterface
{
    $preparer = new EmailDataPreparer();
    $data = $preparer->prepare(...);
}

// Correct: inject EmailDataPreparer via the constructor and call the injected instance
```

# Version
1

Increase this version number whenever this rule file changes.

# PHP Web App Rules

Applies on top of `PHP_RULES.md` when the project is a PHP web app or API (it has `public/index.php`, Slim, Twig or Cycle ORM). Skip it for Composer packages with `"type": "library"`.

## Web Server & Asset Paths

### `.htaccess` — Redirect to Public Folder

Place a root `.htaccess` that rewrites all requests into the `public/` directory:

```apache
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)$ public/$1 [L]
```

Expected directory structure:

```
project/
├── .htaccess              # Root rewrite (above)
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── public/
│   └── index.php          # Application entry point
├── src/
├── templates/
└── vendor/
```

### Asset Paths in Templates

**Always use plain relative paths** for assets — never `../assets/...` or absolute `/assets/...`.

```html
<!-- Correct -->
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/app.js"></script>

<!-- Wrong — breaks when URL is http://localhost/project-name/ -->
<link rel="stylesheet" href="../assets/css/style.css">

<!-- Wrong — breaks when app is in a subfolder -->
<link rel="stylesheet" href="/assets/css/style.css">
```

Why plain relative paths work: `RewriteCond %{REQUEST_FILENAME} !-f` checks if the file exists on disk. Since `assets/css/style.css` exists as a real file, the rewrite is skipped and Apache serves it directly. `../` breaks because the browser resolves it relative to the current URL before the request reaches the server (e.g. `http://localhost/project-name/` + `../assets/...` resolves to `http://localhost/assets/...` which doesn't exist).

---

---

## Template Engine

Keep PHP, HTML, CSS, and JS separated.
This means using a template engine.

Use Twig 3:
```bash
composer require twig/twig
```

Do not put JS code into templates. Use separate `.js` files.

---

## Localization
<!-- steps: review, code -->

Use the php-localization library for multi-language support:
https://github.com/BenjaminKobjolke/php-localization.git

### Installation

Add to `composer.json`:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/BenjaminKobjolke/php-localization.git"
        }
    ],
    "require": {
        "xida/php-localization": "*"
    }
}
```

Then run:
```bash
composer update xida/php-localization
```

### Directory Structure

```
project/
├── lang/
│   ├── en.json    # English (default)
│   ├── de.json    # German
│   └── fr.json    # French
├── src/
└── templates/
```

### Translation File Format

Create `lang/en.json` with nested structure:
```json
{
    "nav": {
        "dashboard": "Dashboard",
        "settings": "Settings",
        "logout": "Logout"
    },
    "auth": {
        "login_title": "Login",
        "signin_subtitle": "Sign in to access your dashboard",
        "error": {
            "auth_failed": "Authentication failed. Please try again.",
            "rate_limited": "Too many attempts. Please try again later."
        }
    },
    "flash": {
        "success": {
            "saved": "Changes saved successfully.",
            "deleted": "Item deleted successfully."
        },
        "error": {
            "not_found": "Item not found.",
            "invalid_id": "Invalid ID."
        }
    },
    "common": {
        "cancel": "Cancel",
        "save": "Save",
        "delete": "Delete",
        "edit": "Edit"
    }
}
```

### PHP Setup

#### Container Integration

Add a method to your DI Container:
```php
use PhpLocalization\Localization;

class Container
{
    private ?Localization $localization = null;

    public function getLocalization(): Localization
    {
        if ($this->localization === null) {
            $this->localization = new Localization([
                'driver' => 'json',
                'langDir' => $this->baseDir . '/lang/',  // trailing slash required
                'defaultLang' => 'en',
                'fallBackLang' => 'en',
            ]);
        }
        return $this->localization;
    }
}
```

#### Controller Helper

Add translation helper to your base controller:
```php
use PhpLocalization\Localization;

abstract class AbstractController
{
    protected function getLocalization(): Localization
    {
        return $this->container->getLocalization();
    }

    protected function t(string $key, array $params = []): string
    {
        return $this->getLocalization()->lang($key, $params);
    }
}
```

Usage in controllers:
```php
// Simple translation
$this->addFlash('success', $this->t('flash.success.saved'));

// With placeholders
$this->addFlash('info', $this->t('messages.welcome', [':name' => $user->name]));
```

### Twig Integration

#### Add the `t()` Function

In your TwigFactory or wherever you configure Twig:
```php
use Twig\TwigFunction;
use PhpLocalization\Localization;

public function create(Localization $localization): Environment
{
    $twig = new Environment($loader, [...]);

    // Add translation function
    $twig->addFunction(new TwigFunction('t', function (string $key, array $params = []) use ($localization) {
        return $localization->lang($key, $params);
    }));

    return $twig;
}
```

#### Usage in Templates

Simple translations:
```twig
<h1>{{ t('nav.dashboard') }}</h1>
<button>{{ t('common.save') }}</button>
<a href="/logout">{{ t('nav.logout') }}</a>
```

With placeholders (define in JSON as `:placeholder`):
```json
{
    "messages": {
        "welcome": "Hello, :name!",
        "items_count": "You have :count items"
    }
}
```

```twig
<p>{{ t('messages.welcome', {':name': user.name}) }}</p>
<p>{{ t('messages.items_count', {':count': items|length}) }}</p>
```

Conditional content:
```twig
{% if error == 'auth_failed' %}
    {{ t('auth.error.auth_failed') }}
{% elseif error == 'rate_limited' %}
    {{ t('auth.error.rate_limited') }}
{% endif %}
```

In attributes:
```twig
<a href="/back" title="{{ t('common.back') }}">
    <i class="icon-back"></i>
</a>

<button onclick="return confirm('{{ t('confirm.delete') }}')">
    {{ t('common.delete') }}
</button>
```

### Translation Key Naming Convention

Use dot notation with logical grouping:
```
section.subsection.key

nav.dashboard          - Navigation items
auth.login_title       - Authentication related
flash.success.saved    - Flash messages by type
flash.error.not_found
form.label.name        - Form labels
form.placeholder.email - Form placeholders
form.validation.required - Validation messages
common.save            - Reusable UI elements
errors.404.title       - Error pages
```

### Translation Keys as Constants

Using raw strings like `t('nav.dashboard')` is error-prone. Create a `TranslationKeys` class with all keys as constants for IDE autocomplete and compile-time error checking.

#### Create `src/Config/TranslationKeys.php`

```php
<?php

namespace App\Config;

final class TranslationKeys
{
    // Navigation
    public const NAV_DASHBOARD = 'nav.dashboard';
    public const NAV_SETTINGS = 'nav.settings';
    public const NAV_LOGOUT = 'nav.logout';

    // Auth
    public const AUTH_LOGIN_TITLE = 'auth.login_title';
    public const AUTH_ERROR_AUTH_FAILED = 'auth.error.auth_failed';

    // Flash messages
    public const FLASH_SUCCESS_SAVED = 'flash.success.saved';
    public const FLASH_ERROR_NOT_FOUND = 'flash.error.not_found';

    // Common
    public const COMMON_CANCEL = 'common.cancel';
    public const COMMON_SAVE = 'common.save';
}
```

#### Add as Twig Global

In TwigFactory, add TranslationKeys as a global:
```php
use App\Config\TranslationKeys;

$twig->addGlobal('TK', new TranslationKeys());
```

#### Usage in Controllers

```php
use App\Config\TranslationKeys as TK;

// Instead of:
$this->addFlash('success', $this->t('flash.success.saved'));

// Use:
$this->addFlash('success', $this->t(TK::FLASH_SUCCESS_SAVED));
```

#### Usage in Templates

```twig
{# Instead of: #}
{{ t('nav.dashboard') }}

{# Use: #}
{{ t(TK.NAV_DASHBOARD) }}
```

#### Benefits

- IDE autocomplete for all translation keys
- Compile-time error if constant doesn't exist
- Easy to find all usages of a key
- Refactoring support

### Adding New Languages

1. Copy `lang/en.json` to `lang/de.json`
2. Translate all values (keep keys identical)
3. Change language in configuration:

```php
$this->localization = new Localization([
    'driver' => 'json',
    'langDir' => $this->baseDir . '/lang/',
    'defaultLang' => 'de',  // Changed to German
    'fallBackLang' => 'en', // Falls back to English if key missing
]);
```

### Reference

Full documentation: https://github.com/BenjaminKobjolke/php-localization/blob/master/README.md

---

---

## Database: Cycle ORM Relations & Query Builder
<!-- steps: review, code -->

### No Raw SQL

**Never use raw SQL or DBAL direct queries.** Always use Cycle ORM relations and the query builder. Raw SQL causes bugs when entity columns change — every SELECT must be manually updated. ORM relations automatically include all fields.

### Defining Relations (PHP 8 Attributes)

Always define entity relationships using Cycle ORM relation attributes. Use `fkCreate: false` and `indexCreate: false` when indexes already exist or FK constraints are not desired.

```php
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\Annotated\Annotation\Relation\HasOne;
use Cycle\Annotated\Annotation\Relation\HasMany;
```

#### BelongsTo (child stores FK to parent)

```php
// Required parent (non-nullable FK)
#[BelongsTo(target: BankAccount::class, innerKey: 'bank_account_id', fkCreate: false, indexCreate: false)]
public ?BankAccount $bankAccount = null;

// Optional parent (nullable FK)
#[BelongsTo(target: Category::class, innerKey: 'category_id', nullable: true, fkCreate: false, indexCreate: false)]
public ?Category $category = null;
```

**Key parameters:** `target` (parent class), `innerKey` (FK column in this entity), `nullable` (default false), `fkCreate` (create DB constraint, default true), `indexCreate` (create index, default true).

The existing column property (e.g., `public int $bankAccountId`) must remain — Cycle ORM uses both the column and the relation property.

#### HasOne (parent owns one child; child stores FK)

```php
#[HasOne(target: Expense::class, outerKey: 'bank_transaction_id', nullable: true, fkCreate: false, indexCreate: false)]
public ?Expense $expense = null;
```

**Key parameters:** `target` (child class), `outerKey` (FK column in child entity), `nullable` (default false).

#### HasMany (parent owns many children)

```php
#[HasMany(target: Post::class, outerKey: 'user_id', orderBy: ['created_at' => 'DESC'])]
private array $posts = [];
```

**Key parameters:** `target`, `outerKey`, `where` (auto-filter), `orderBy` (default sort).

### Eager Loading with `load()`

Use `load()` to preload relation data and avoid N+1 queries:

```php
// Load single relation
$this->select()
    ->load('bankAccount')
    ->load('category')
    ->load('expense')
    ->where('bank_account_id', $bankAccountId)
    ->orderBy('date_actual', 'DESC')
    ->fetchAll();

// Nested relations (dot notation)
$this->select()->load('posts.comments.author')->fetchAll();
```

### Filtering by Relations with `with()`

Use `with()` to join a relation for WHERE conditions without loading its data:

```php
// Filter transactions by related bank account's company
$this->select()
    ->with('bankAccount')
    ->where('bankAccount.companyId', $companyId)
    ->fetchAll();
```

**Key difference:** `load()` = preload relation data. `with()` = join for filtering only.

### Entity Output with Relations

Add a `toDetailArray()` method for enriched output that includes relation data:

```php
public function toDetailArray(): array
{
    return [
        ...$this->toArray(),
        'category_label' => $this->category?->label,
        'expense_id' => $this->expense?->id,
    ];
}
```

Only call `toDetailArray()` on entities fetched with `->load()`.

### Aggregate Queries (SUM, COUNT, GROUP BY)

Use `buildQuery()` to access the DBAL-level query builder for aggregates:

```php
use Cycle\Database\Injection\Fragment;
use Cycle\Database\Injection\Expression;

// Simple aggregate
$total = $this->select()->where('status', 'active')->count();

// Complex aggregates with GROUP BY
$this->select()
    ->with('bankAccount')
    ->where('bankAccount.companyId', $companyId)
    ->buildQuery()
    ->columns([
        'bt.category_id',
        new Fragment('SUM(CASE WHEN CAST(bt.value AS DECIMAL(15,2)) > 0 THEN CAST(bt.value AS DECIMAL(15,2)) ELSE 0 END) AS income'),
        new Fragment('COUNT(*) AS transaction_count'),
    ])
    ->groupBy('bt.category_id')
    ->run()
    ->fetchAll();
```

**Fragment vs Expression:**
- `Fragment`: Raw SQL, no escaping. Use for SQL functions, subqueries.
- `Expression`: Auto-quotes column identifiers. Use when referencing columns inside functions.

### Dynamic WHERE Conditions

Build dynamic conditions using chained `where()` calls on the query builder:

```php
$query = $this->select()
    ->with('bankAccount')
    ->where('bankAccount.companyId', $companyId);

if ($pattern !== null) {
    $query->where('name', 'LIKE', '%' . $pattern . '%');
}

if ($amountMin !== null && $amountMax !== null) {
    $query->where(new Fragment('ABS(CAST(value AS DECIMAL(15,2)))'), '>=', $amountMin)
          ->where(new Fragment('ABS(CAST(value AS DECIMAL(15,2)))'), '<=', $amountMax);
}

return $query->orderBy('date_actual', 'DESC')
    ->limit($limit)->offset($offset)
    ->fetchAll();
```

---

---

## Essential Rules

### Required Batch Files

Every project must include:

- `tools/run_tests.bat` - unit suite, no database, works offline
- `tools/run_integration_tests.bat` - integration suite against a local mirror of the live DB
- `tools/tests.bat` - everything; refreshes the mirror first

Set them up per [`../php_setup_files/FAST_TESTS.md`](../php_setup_files/FAST_TESTS.md) - local DB
mirror via `sqlbackup`, Cycle schema cached once per process, `XDEBUG_MODE=off`, exit code =
phpunit's. Templates for all four bats live in `../php_setup_files/tools/`.

---

### Middleware

Use PSR-15 middleware for cross-cutting concerns such as authentication, CORS, logging, and rate
limiting. This keeps controller code focused on business logic and avoids duplicating infrastructure
code across routes.

---

### Configuration Files

Use plain PHP config files instead of `.env` files. Do not use `vlucas/phpdotenv`.

#### Structure

```
project/
├── config/
│   ├── app.php           # General application settings (gitignored)
│   ├── app.php.example   # Template for app.php
│   ├── database.php      # Database connection settings (gitignored)
│   └── database.php.example  # Template for database.php
```

#### config/app.php.example

```php
<?php

declare(strict_types=1);

return [
    'jwt_secret' => 'your-secret-key-change-in-production',
    'jwt_lifetime' => 86400,
    'debug' => true,
];
```

#### config/database.php.example

```php
<?php

declare(strict_types=1);

use Cycle\Database\Config;

$dbHost = 'localhost';
$dbPort = 3306; // local server must match live's engine (WAMP MariaDB: 3307) — see ../php_setup_files/FAST_TESTS.md
$dbName = 'your_database';
$dbUser = 'root';
$dbPass = '';

return new Config\DatabaseConfig([
    'default' => 'default',
    'databases' => [
        'default' => ['connection' => 'mysql'],
    ],
    'connections' => [
        'mysql' => new Config\MySQLDriverConfig(
            connection: new Config\MySQL\TcpConnectionConfig(
                database: $dbName,
                host: $dbHost,
                port: $dbPort,
                user: $dbUser,
                password: $dbPass,
            ),
            queryCache: true,
        ),
    ],
]);
```

#### Usage in Code

```php
// Load app config
$config = require __DIR__ . '/../config/app.php';
$jwtSecret = $config['jwt_secret'];

// Load database config (returns DatabaseConfig object)
$dbConfig = require __DIR__ . '/../config/database.php';
```

#### .gitignore

Always exclude the actual config files, only commit the examples:

```
config/app.php
config/database.php
```

#### Setup Instructions (for README.md)

```bash
cp config/app.php.example config/app.php
cp config/database.php.example config/database.php
# Edit both files with your credentials
```

---

---

## Database Timezone Handling

When storing DATE type fields in a database using an ORM (Cycle, Doctrine, Eloquent), be careful with timezone handling. Creating a `DateTimeImmutable` from a date string without an explicit timezone can cause dates to shift by one day.

### The Problem

```php
// Server timezone: Europe/Paris (UTC+1)
$day = new DateTimeImmutable('2026-01-15');
// Creates: 2026-01-15 00:00:00+01:00

// When ORM stores as DATE type, it may convert to UTC:
// 2026-01-15 00:00:00+01:00 → 2026-01-14 23:00:00 UTC
// DATE becomes: 2026-01-14 (one day earlier!)
```

### The Solution

Always use explicit UTC timezone with noon time for DATE fields:

```php
$day = DateTimeImmutable::createFromFormat(
    'Y-m-d H:i:s',
    $dateString . ' 12:00:00',
    new \DateTimeZone('UTC')
);
```

### Why Noon?

Using noon (12:00) instead of midnight provides a safety buffer:
- Noon UTC is within the same calendar day for all timezones (UTC-12 to UTC+14)
- No timezone conversion can shift noon to a different day
- Makes code resilient to any server timezone configuration

### When This Pattern is Needed

- **DATE type columns**: Any field that stores only a date without time
- **User-provided date strings**: When parsing `Y-m-d` format from API requests
- **Date comparisons in queries**: Ensure consistent date handling

### When This Pattern is NOT Needed

- **DATETIME/TIMESTAMP columns**: These store full timestamps with timezone info
- **"Now" calculations**: `new DateTimeImmutable()` for current time is fine
- **Timestamps** (`created_at`, `updated_at`): These are meant to store exact moments

### Testing

Always include unit tests that verify date handling across different timezones:

```php
public function testDatePreservedAcrossTimezones(): void
{
    $inputDate = '2026-01-15';
    $originalTz = date_default_timezone_get();

    foreach (['UTC', 'Europe/Paris', 'America/New_York'] as $tz) {
        date_default_timezone_set($tz);

        $day = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $inputDate . ' 12:00:00',
            new \DateTimeZone('UTC')
        );

        $this->assertSame($inputDate, $day->format('Y-m-d'));
    }

    date_default_timezone_set($originalTz);
}
```

---

---

## Value Objects for `$_POST` / `$_GET` Boundaries

Project-input arrays (`$_POST`, decoded request bodies, file-upload payloads) are the
legitimate source of raw associative arrays. Validate at the boundary and produce a typed
object **once**; never let `$_POST['some_key']` flow through three managers.

```php
// At the controller boundary
$payload = ContestSettingsPayload::fromPostArray($_POST);
if (!$payload->isValid()) {
    RedirectManager::backWithErrors($payload->errors());
    return;
}

// Everywhere downstream
$this->saveSettings($payload);   // type: ContestSettingsPayload
```

Pair with the existing "Input Validation at Boundaries" rule — that rule says *validate*;
this rule says *what the validated result should be*: a typed object, not a sanitized array.

---

# Version
3

Increase this version number whenever this rule file changes.

# Server-Rendered PHP Frontend Rules

These rules apply to PHP web applications that route requests and render HTML on the server,
commonly with Twig. The application owns page rendering and may use a local database, call one or
more APIs, or do both. It is neither a REST API project nor a browser-rendered single-page app.

It covers server-side routing, route names, template rendering, application configuration, static
and media assets, and deployment boundaries.

Pair with `COMMON_RULES.md`, `PHP_RULES.md`, and `php/APP.md`. Add `DESIGN_RULES.md` for its user
interface and add rules for asset languages or build tools the project actually uses, such as
`SCSS_RULES.md` or `TYPESCRIPT_RULES.md`.

---

## Request Routing and Controllers

- Keep the web server's document root on the public directory. Route requests through one front
  controller when the framework requires it; keep route declarations in the framework's routing
  configuration rather than duplicating path checks across controllers.
- Give routes stable names and generate links and redirects from those names. Keep URL paths,
  HTTP methods, middleware, and controller actions explicit and consistent.
- Keep controllers as the boundary that translates an HTTP request into application work and a
  response. Put domain decisions and persistence in existing services and repositories; do not
  put SQL, API transport, or business workflows in route declarations or templates.
- Validate path, query, form, and uploaded-file input at the request boundary. Convert accepted
  input into typed values before passing it to application services.
- Use POST/redirect/GET for ordinary form submissions. Protect state-changing requests with CSRF
  tokens and the framework's method and authorization checks.

---

## Rendering with Twig

- Keep Twig autoescaping enabled and strict variable checks on outside production if the framework
  supports that split. Never mark user-provided or externally sourced content as raw HTML without
  an explicit sanitization boundary.
- Controllers or page services assemble the data a page needs and pass it as template context.
  Templates render that context; they do not query a database or make network requests.
- Put shared page structure and reusable fragments in base layouts and components. Keep
  page-specific markup in page templates, and share form markup through partials when create and
  edit forms have the same fields.
- Keep presentation and localization in templates and translation resources. Put decisions that
  affect permissions, prices, or persisted state in application code.
- Use the project's URL and asset helpers to build links. Avoid hand-joining relative paths, which
  resolve differently from nested routes or an application deployed below a URL prefix.

---

## Application Configuration and State

- Load environment-specific values through the project's central configuration layer. Commit an
  example configuration that names required keys, but never commit secrets or machine-specific
  values.
- Keep authentication and session state server-side where possible. Configure session cookies as
  `HttpOnly`, `Secure` in HTTPS environments, and an appropriate `SameSite` mode; regenerate the
  session identifier after authentication changes.
- Apply authorization on the server for every protected page and mutation. Hiding a link or button
  is not access control.
- Store durable application data through the project's existing repository or ORM conventions.
  A local database is optional; do not add a database mirror solely because a page consumes an API.

---

## Data Sources and API Consumers

Use the application's existing service and repository boundaries for local data. When a page needs
data from an external or separately owned API, keep HTTP calls in the shared API client or adapter,
and map responses to typed values before they reach controllers or templates.

The following guidance applies only when the application consumes a separate API:

- Keep API transport, authentication headers, error mapping, and response decoding in the shared
  client. Never expose server credentials or bearer tokens to browser JavaScript.
- Request the independent data needed for a page together where practical; avoid a detail request
  for every row in a rendered list.
- Follow the API's paging, sorting, and filtering contract. Request a missing capability from the
  API owner instead of rebuilding a complete collection in the PHP frontend.
- On authentication expiry, clear invalid server-side session state and use the application's
  login flow. Keep this behavior centralized rather than repeating it in page controllers.

---

## Static and Media Assets

- Keep source assets separate from generated bundles. Edit source styles and scripts, and use the
  project's build command to produce generated CSS and JavaScript.
- Generate asset URLs through the shared asset helper so the deployment base path and cache-busting
  version are handled consistently. Do not hard-code root-relative URLs when the app may be mounted
  under a prefix.
- Serve only intended public assets from the document root. Keep source files, configuration,
  uploaded originals, and other private data outside it unless they are deliberately published.
- Validate media uploads by size, allowed content type, and file content. Generate server-side
  filenames, prevent executable uploads, and store private media outside the public directory with
  access controlled by the application.
- Use stable cache headers for fingerprinted static assets. Ensure deployment publishes the
  generated files referenced by the rendered pages.

---

## Deployment Boundary

- Treat the web server and application as separate configuration boundaries: document the public
  document root, front-controller fallback, PHP runtime requirements, and any required rewrite or
  proxy settings.
- Keep secrets and environment-specific configuration on the target environment. Verify required
  configuration at startup and fail with a useful server-side message rather than rendering a
  partially configured page.
- Build frontend assets before release and deploy the matching generated files with the PHP code.
  Do not edit generated bundles by hand.
- Run database migrations as an explicit deployment step only when the application owns a database.
  Keep API migrations and deployments in the API project's release process.
- Document writable runtime directories, uploaded media handling, cache clearing, and safe release
  rollback where they apply. Do not make the entire application tree writable by the web server.

---

## Tests

- Test route registration, controller responses, template rendering, validation, and authorization
  at the narrowest useful level.
- Use temporary databases or the project's database test setup when exercising persistence. Stub
  external API clients so tests do not depend on a live service.
- Include integration coverage for the application's request-to-render path and for its error
  responses. Keep the test runner's exit code intact in project scripts.

# Version
4

Increase this version number whenever this rule file changes.

# Design Rules (All Languages)

See `COMMON_RULES.md` for rules that apply to all languages. These rules apply to
anything with a user interface — desktop app, web frontend, mobile app, CLI TUI.

They are about **decisions**, not about a framework. The per-stack implementation
guides are:

- `python_setup_files/MODERN_GUI.md` — PySide6 desktop apps
- `rust/SLINT.md` — Slint desktop apps (Rust)
- `project_type/FRONTEND.md` — web SPAs (Vite, Tailwind, dark mode)
- `project_type/SERVER_RENDERED_FRONTEND.md` — server-rendered PHP web frontends (Twig, Bootstrap)

Default UI toolkits ship a *safe* look, not a good one: gradient buttons, boxed tabs,
every control the same size, no hierarchy. An interface built from defaults reads as a
debug tool no matter how good the code behind it is.

---

## Tokens, Not Values

Every color, spacing step, radius, and font size is a **named token defined once**.
Call sites reference the token; nothing hardcodes `#2B2B2B` or `13px`.

- **Anti-pattern.** A hex color appears on four screens. Changing the theme means
  finding all four, and the one you miss is the one the user sees.
- **Correct pattern.** One palette/theme module (a frozen dataclass, a CSS custom
  property block, a `ThemeData`) that every screen reads.
- This is "String Constants" and DRY applied to design. The moment a value appears
  twice, it is a token.

---

## One Accent Color

Pick **one** accent and use it only for: the selected navigation item, the focus ring,
the primary action, and progress. Everything else is a neutral.

- An interface where the accent frames the window, fills the title bar, and outlines
  every button has no accent — it has a background color.
- Semantic colors (success green, error red) are separate tokens and do **not** follow
  the accent. If a status color must match another app or a spec, comment the field
  saying so, so a theme change does not silently redefine it.
- Never encode information in color alone — pair it with a glyph, a label, or a
  position (colorblind users, grayscale printing, low-quality projectors).

---

## Neutral Ramp Before Color

A dark theme needs at least: window background, an elevated surface, a control fill,
its hover and pressed states, a border, primary text, and muted text. Most of a good
interface is that ramp; color is the exception on top of it.

Body text on the background must clear **4.5:1** contrast (3:1 for large text and for
UI borders that carry meaning). Muted text is muted, not unreadable — check it, don't
eyeball it.

---

## Hierarchy Is Type and Space, Not Boxes

Three levels are usually enough: a page heading, a muted one-line explanation, and body
or control text. Give each a token (size + weight), and build them with a **shared
factory** (`heading()` / `subheading()`), never by repeating the styling on each screen.

- Separate groups with whitespace before you separate them with a border, and with a
  border before you separate them with a filled box.
- A screen that is all boxes has no hierarchy — everything is equally important, which
  means nothing is.
- Use a consistent spacing scale (4/8/12/16/24…) rather than whatever number closed the
  gap on that one screen.

---

## Size Controls by Importance

Controls should not all be the same size, and the primary action should not be the same
weight as its neighbors. A grid of six identical giant buttons is as flat as a grid of
six identical tiny ones.

- Primary action: filled, accent.
- Secondary: outlined or flat with accent text.
- Rare actions (a custom value, an advanced group): folded away behind a toggle or a
  collapsed section — present, not prominent.

---

## Every Focusable Control Has a Visible Focus State

The moment you give a control a custom background, the toolkit's own focus ring
disappears. Re-add it explicitly, in the accent, on **every** focusable control —
buttons, fields, lists, tabs.

Keyboard users, accessibility tooling, and your own automated tests all navigate by
focus. This is not decoration; a UI with an invisible focus ring is unusable without a
mouse. Tab through the entire interface once before calling a restyle done.

---

## Never Set a Minimum Size to Fix a Layout

If content is too tall or too wide, make it **scroll or wrap** — do not pin the window.
A minimum size set to fix one screen becomes a floor every other screen inherits,
including screens the user is not looking at (tab and stack containers report their
largest child's minimum as their own).

- Wrap every page/route in a scrolling container.
- Word-wrap explanatory text; an unwrapped sentence sets the width floor.
- Then pin it with a test: assert the window resizes to something tiny, and assert the
  stylesheet contains no minimum-size rule.

---

## Own the Window Chrome

On desktop, the title bar and window frame belong to the OS and default to the user's
system accent color. A carefully themed dark app with a bright green caption bar looks
broken. Set the caption and border explicitly, and make the call a **silent no-op** on
platforms and OS versions that do not support it.

---

## Icons: Vendor a Subset, Recolor in Code

- Vendor the SVGs you actually use into the repo, with the upstream `LICENSE` and a
  header naming the set and version. Never fetch icon assets at runtime, and never add
  a whole icon-font dependency for six glyphs.
- Recolor from a palette token in code rather than committing one file per color.
- Cache by `(name, color)`.
- Keep icon sizes on the same scale as the type — an icon that is not aligned to the
  text baseline or optical center reads as a mistake.

---

## Design the Empty, Loading, and Error States

Every list has a first run. Every request fails sometimes. A screen designed only for
the happy path ships a blank rectangle to every new user.

- **Empty**: say what would be here and how to get one.
- **Loading**: keep the layout stable — never let content jump when it arrives.
- **Error**: say what failed and what to do next, in the interface, not only in a log.
- **Validation**: show it where the input is, not in a dialog. If the user can see the
  field, tell them there instead of silently falling back to a default.

---

## Follow the Platform, Then Be Consistent

Match the platform's conventions for window controls, menus, shortcuts, and dialogs
before inventing your own. Inside your app, be *ruthlessly* consistent: the same action
looks the same everywhere, the same spacing separates the same kinds of things, the
same word means the same thing on every screen.

If two of your own screens disagree, the screens are wrong — not the user.

---

## Localize From the Start

Every user-visible string goes through the translation layer with a key constant, never
a raw string at a call site. Layouts must survive a translation that is 40% longer:
wrap text, avoid fixed-width labels, and never build a sentence by concatenating
fragments — translate the whole sentence with placeholders.

---

## Verify Visually, and Automate It

A restyle is not done because the tests pass — tests do not look at the screen.

- Render each screen to an image and actually look at all of them after a change.
  Offscreen/headless rendering is fine for quick, deterministic checks.
- Automatic screenshots of the real running app are allowed and wanted — to verify a
  task and to produce promotional images (store pages, manuals). Take them with the
  `automated-application-screenshots` tool and the app's connector (Python, Flutter or
  web). The connector drives the app from inside its own process — calling a button's
  click, setting a field's text, opening a screen — so no input can reach another
  application.
- Check the app at its smallest supported size, not just the size it opens at.
- Automate what an image cannot assert: no minimum-size rule in the theme, a focus style
  on every control, a recolored icon that still has pixels.
- Never send OS-level synthetic mouse or keyboard input (moving the cursor, injected
  clicks or key presses) to a real window — the moment focus moves, that input lands in
  someone else's application. This forbids the input method, not screenshots.

---

## Checklist

- [ ] One token module; no color, size, or radius literal at a call site
- [ ] One accent, used for selection/focus/primary only
- [ ] Body text ≥ 4.5:1 contrast; information never carried by color alone
- [ ] Heading / subheading / body built by a shared factory
- [ ] Consistent spacing scale; whitespace before borders before boxes
- [ ] Visible focus state on every focusable control, verified by tabbing through
- [ ] Nothing sets a minimum size; content scrolls and wraps instead
- [ ] Window chrome themed, with a no-op fallback
- [ ] Icons vendored with license, recolored from tokens, cached
- [ ] Empty / loading / error / validation states designed
- [ ] All strings localized via key constants; layout survives longer translations
- [ ] Every screen rendered and looked at after the change

# Version
15

Increase this version number whenever this rule file changes.

# graphify Knowledge Graph (Optional Addon)

graphify turns a code folder into a queryable knowledge graph — god nodes, communities,
cross-file relationships, fan-in/fan-out. Use it to orient before grep and to spot god classes.

**Keep the build AST-only (no LLM, no API cost) by scoping to the code dir.** graphify runs a
free deterministic AST pass on *code* files, but a separate **LLM pass** on every *non-code*
file — docs, `.md`, `.txt`, config `.yaml`, images (vision), audio/video (whisper). That pass
needs an LLM (Gemini key or the host agent) and costs tokens. A build scoped to a pure-code dir
(`lib/`, `src/`, `app/`) has zero non-code files, so it skips the LLM pass entirely and rebuilds
need no AI. A repo-root build (`/graphify .`) sweeps in `docs/`, `README.md`, icons, and sound
assets — forcing the LLM pass. **Always scope to the code dir; never build the repo root.**

---

Setup instructions live in the coding-rules plugin at `graphify_setup_files/SETUP.md`.

## Folder layout (know which is which)
<!-- steps: code -->

- `graphify-out/` at the **project root** = the **live graph** (`graph.json`, `GRAPH_REPORT.md`,
  `graph.html`). The only one queries read. Keep it `directed=True`.
- `graphify-out/cache/` at the project root = AST cache written by the CLI refresh (it runs
  with `GRAPHIFY_OUT` pointing at the root folder, see "Refreshing"). Scratch only.
- `<code-dir>/graphify-out/` = legacy AST cache from the skill build. Never the live graph
  under the documented flow; safe to delete. If a `graph.json` ever appears in there, a bare
  `graphify update` ran without `GRAPHIFY_OUT` — delete that `graph.json`, keep `cache/`.

## What the graph knows (and does not)

- Knows: code structure — classes, methods, calls, references, extends/implements, plus
  fan-in/fan-out and community / god-node structure.
- Does NOT know: business rules, API response shape, or rendered template/view output. It is a
  snapshot — stale until rebuilt. Constants referenced by string can appear as isolated nodes
  (AST limitation, not a missing dependency).

---

## Using the graph

- For codebase questions, run `graphify query "<question>"` first when `graphify-out/graph.json`
  exists. `graphify path "<A>" "<B>"` for relationships; `graphify explain "<concept>"` for a
  focused node. These return a small scoped subgraph vs. reading GRAPH_REPORT.md or raw grep.
- Judge coupling by direction: high **fan-in** + low fan-out (shared base / constants / DTO) is
  healthy; high **fan-out** (>~20 outgoing deps) is god-class risk and a refactor signal.
- Read `graphify-out/GRAPH_REPORT.md` only for broad architecture review, or when
  query/path/explain do not surface enough context.

## Manual refresh bat (`tools/graphify_update.bat`)
<!-- steps: code -->

Copied from the `graphify_update.bat` template beside this addon, adjusted (`CODE_DIR`).
Run it from anywhere — it `pushd`es to the repo root itself.

- **Does:** (1) the live-graph CLI refresh from "Refreshing after a code change" in
  `IMPLEMENTATION_FLOW.md` (sets `GRAPHIFY_OUT` to the root
  `graphify-out\`, passes the absolute code dir); (2) smoke test — prints the root
  graph's `directed` flag + node count, `god-nodes`, a sample `query`.
- **Does NOT:** re-extract docs (see "Doc changes" in `IMPLEMENTATION_FLOW.md`) or build a first graph — that is the
  skill flow (`/graphify <code-dir> --directed`).
- **When to use:** after a code change outside an AI session, or as a "is graphify still
  wired up?" check after cloning, a dependency change, or a graphify upgrade.

## In-tree vendored code — exclude it, scoping alone won't
<!-- steps: code -->

`--directed`-scoping the build to `<code-dir>` keeps external `vendor/`/`node_modules/` out
automatically, but a **committed** third-party library living *inside* `<code-dir>` (a bundled
SDK, a copied library folder) is not gitignored, so graphify scans it like first-party code.
Symptom: god-nodes / oversized communities in `GRAPH_REPORT.md` whose class names belong to a
library, not the app (e.g. hundreds of `Facebook*`/`GraphNode*` nodes from an in-tree Facebook
SDK).

Fix once per project:

1. Spot the vendored folder(s) under `<code-dir>` (e.g. `application/libs/`). Committed
   **asset/sprite dirs** are the same kind of noise — bundled UI images (jQuery-UI/colorbox
   sprites, e.g. `extensions/backend/assets/images/`) aren't code but graphify still scans them;
   exclude them the same way.
2. Drop a `.graphifyignore` at the scan root (gitignore syntax, honored by default):
   ```
   # Vendored / third-party code + bundled assets — not our architecture, noise in the graph
   libs/
   ```
3. Rebuild with the CLI refresh plus `--force` — a narrower corpus is a *smaller* graph,
   which trips the shrink guard (#479):
   `GRAPHIFY_OUT="$PWD/graphify-out" graphify update --force "$PWD/<code-dir>"`.
   Do NOT delete `graphify-out/graph.json` first (see "Refreshing after a code change" in
   `IMPLEMENTATION_FLOW.md`: the CLI would rebuild
   it undirected and drop the doc nodes).
4. Verify: grep the vendored library's distinctive class name in the new `graph.json` — it
   should return only first-party code that *uses* the library (e.g. your own `FacebookManager`),
   never the library's own classes.
