# emps-factory — Project Instructions for Claude

## Stack

PHP + EMPS framework, MySQL, Smarty templates, Vue 2.6, Bulma CSS.

Future upgrade - EMPS to EMPS6. The project currently runs on EMPS 4.5.

## EMPS Documentation

Refer this repository for documentation on the EMPS framework used
in this project:

https://github.com/AlexGnatko/EMPS - the old 4.5 version currently used 
in the project.

https://github.com/AlexGnatko/EMPS6 - a newer version of the same framework,
better documented, but having some features not present in EMPS 4.5.

EMPS website:

https://emps.ag38.ru/

## EMPS6 Findings (portable — reuse this section in other EMPS projects)

Lessons learned working with EMPS6 that aren't obvious from a first read of
the docs at https://emps.ag38.ru/docs/. Framework-general, not specific to
this project — copy this whole section into `CLAUDE.md` of other EMPS6
projects.

### SQLSync tables are not actually temporary

`modules/{module}/sql/module.sql` files are written as
`CREATE TEMPORARY TABLE temp_xxx (...)`, but this is NOT a staging/scratch
table — it's the **declarative live schema**. Visiting `/sqlsync/{module}/`
strips the `temp_` prefix (replacing it with the real `TP` prefix), drops
`TEMPORARY`, and creates the table if missing or `ALTER TABLE`s it into
matching shape if it already exists. It's idempotent — re-run any time after
editing the SQL file. Don't go looking for a separate "real" schema file
elsewhere; this file *is* it. See https://emps.ag38.ru/docs/sqlsync/.

### Name your main text column `name`, not `title`/`label`

`<selector>`/`<descriptor>` in entity-search mode (`type="table_name"
:search="true"`) are backed by `modules/pick/ng/` (`EMPS_NG_PickList` /
`EMPS_NG_PickDescribe` core classes, extended per-project as
`EMPS_Local_PickList` / `EMPS_Local_PickDescribe`). **By default they
search/display a column literally called `name`.** If a table's main text
column is called anything else, the picker silently returns nothing (or a
blank label) — no error. Fix: either name the column `name` up front, or add
a per-table `else if ($this->table_name == "...")` case to both
`modules/pick/ng/list/project.php` and `modules/pick/ng/describe/project.php`
(pattern: mirror the existing cases already in those files). Prefer naming
it `name` — it's free, the override isn't.

### `<selector>` is not for enums

There is no built-in "enum mode" for `<selector>` — `type` always resolves
through `pick/ng`, which is for picking a database row by id. For a
status/type field backed by `enum.nn.txt`, use a plain
`<select v-model="row.status">` with `v-for="item in enums['x']"`, populated
via `EMPS.vue_load_enums(this, "x")` and displayed with `enum_val('x', code)`
(from `common_mixin`, `/mjs/comp-mixins/common.js`). Don't wire enums into
`pick/ng` — it works but is more code for a worse result than a `<select>`.

### Table column conventions

- `id` — `bigint NOT NULL AUTO_INCREMENT`, primary key.
- `cdt` / `dt` — created / last-modified timestamps as `bigint` **Unix
  timestamps**, not SQL `DATETIME`.
- `status` (or a specific name like `pub`) — an int/bigint code paired with
  an entry in `enum.nn.txt`, usually with a matching `..._class` enum
  mapping each code to a Bulma color class (`is-success`, `is-dark`, etc.)
  for badges/tags. **Exception**: purely internal/service tables (e.g. a
  scraper job queue) that will only ever be inspected via
  `$emps->plaintext_response()` endpoints — never through a Vue admin
  screen — don't need an `enum.nn.txt` entry for their status codes. Plain
  ints with a PHP comment are enough; skip the enum ceremony until (if ever)
  a UI actually needs it.
- `<other_table>_id` — foreign keys named after the target table, singular,
  + `_id` (`author_id`, `remedy_id`). This is what `<selector>`/`<descriptor>`
  and hand-written joins expect.

### Smarty + Vue share `{{ }}` delimiters — the real rule is the space, not the keyword

**Correction (an earlier version of this note was wrong — verified against
https://emps.ag38.ru/docs/smarty-vue/ after it broke a page in
`box.irkdelikates.ru` with `Unexpected "."`):** it is NOT about whether the
tag starts with a recognized Smarty keyword. The actual rule is purely
lexical: **`{{` immediately followed by a non-space character is a Smarty
tag, parsed on the server. `{{` followed by a space is left untouched for
Vue.** E.g. `{{$product.name}}` → Smarty; `{{ modal_item.name }}` → passed
through for Vue (note the space — that's what actually matters, not
"modal_item" being a non-keyword). A bare Vue-only expression written
*without* the leading space, like `{{modal_item.name}}`, gets fed to
Smarty's parser and fails outright the moment it hits something Smarty's
grammar doesn't expect (a `.` after a bare word, in that example) — it does
not silently pass through just because the word isn't a Smarty keyword.
Always add the leading space for anything meant for Vue.

Separately: any `{{script ...}}` / `{{include file="db:_comp/..."}}`
component-registration tags in a `.nn.htm` file must be wrapped in
`{{capture append="vue_out"}}...{{/capture}}` (flushed later outside
`<div id="mainapp">`, since Vue can't tolerate `<script>`/template tags
inside its mount element). When writing *documentation* `.md` files that go
through the same Smarty pipeline, wrap any code sample containing literal
`{{`/`}}` (Smarty or Vue) in `{{literal}}...{{/literal}}` or Smarty will try
to parse it and break the page.

### Be skeptical of the EMPS docs site itself

`emps.ag38.ru/docs/` is hand/AI-maintained and can contain stale or
never-actually-tested claims (e.g. it used to document a nonexistent
`<selector>` "enum mode"). If something documented there doesn't match
observed behavior, say so and ask before propagating it further, rather than
trusting the docs as ground truth.

## EMPS Core Mechanics (portable — applies to EMPS 4.5 and EMPS6, copy this whole section into `CLAUDE.md` of other EMPS projects)

Documented at https://emps.ag38.ru/docs/file-structure/ and
https://emps.ag38.ru/docs/url-variables/ — confirmed to hold for this
project's EMPS 4.5 too (empirically, via `modules/test/ukassa/ukassa.php`).
EMPS 4.5's core isn't in this repo (bootstrapped externally via
`EMPS/4.5/emps_bootstrap.php`), so treat the docs site as the source of
truth here rather than grepping this repo's modules for examples — module
folder layout alone does *not* tell you the URL.

### URL segments map to fixed variables, in order — only the first segment picks the module

The URL (path only, no host/`?`/`#`) is split on `/` into segments, which are
assigned **in order** to the globals named in `EMPS_URL_VARS`:
`pp, key, start, ss, sd, sk, sm, sx, sy` (rarely anything past `sd` is used
in practice). E.g. `/manage-orders/1/-/info/` → `$pp = "manage-orders"`,
`$key = "1"`, `$start = ""` (a literal `-` segment means "empty"),
`$ss = "info"`.

Only `$pp` (the *first* segment) determines the module: **hyphens inside
`$pp` are replaced with slashes** to get the folder path, and EMPS looks for
a PHP file there named after the *last* folder. So `manage-orders` →
`modules/manage/orders/orders.php`. Every other URL segment is *not* a
nested folder lookup — it's just the next positional variable (`$key`,
then `$start`, then `$ss`, ...).

**Practical consequence**: a module living at `modules/{a}/{b}/{b}.php` is
reached at `/{a}-{b}/`, not `/{a}/{b}/` — confirmed for
`modules/test/ukassa/ukassa.php` → `/test-ukassa/` (not `/test/ukassa/`).
Anything after that hyphenated first segment becomes `$key` (then `$start`,
etc.), not more nested path. Don't infer a new (or existing) module's URL
from its folder path by eyeballing slashes — either derive it from this rule
(hyphen-join every folder level down to the file, then remaining segments
are `$key`/`$start`/...) or ask/test before writing docs or webhook URLs
that depend on it.

### Prefer `elink()`/`slink()`/`clink()` over hand-built URLs

When generating a link to another module/page *from PHP code* (redirects,
`metadata` return URLs, links assigned to Smarty), use `$emps->elink()`
(builds a URL from the current `$pp`/`$key`/... globals), `$emps->slink($value,
$var)` (sets one URL var, then returns `elink()`) or `$emps->clink($extra)`
(`elink()` plus an appended query fragment) instead of concatenating strings
by hand. This sidesteps exactly the hyphen-vs-slash mistake above — you don't
have to remember the encoding rule if the framework generates it for you.
For truly external URLs (e.g. a payment gateway's webhook/return URL, which
has to be a plain absolute string outside any PHP request context) you still
have to hand-build it — that's where the rule above matters and where this
project's `ukassa` module got it wrong the first time.

### Referencing files virtually: `$emps->page_file_name()`

Don't `require_once` a hardcoded relative path for a project or core class —
use the virtual-name lookup so EMPS can resolve it across this app / a
parent app (if any) / the EMPS base install, in that order:

```php
require_once $emps->page_file_name('_orders,orders.class', 'controller');
// -> modules/orders/orders.class.php
```

Syntax: `'_<path-under-modules>,<filename-without-extension>'` (the leading
`_` means "look under `modules/`"), plus a type argument — `'controller'`
(`.php`), `'view'` (`.{lang}.htm`, falling back to `.nn.htm`), or `'inc'`. If
there's no comma, EMPS assumes the file is named the same as its containing
folder. The same virtual-name syntax (with a `db:` prefix) is used for
Smarty `{{include file="db:_orders,itemblock"}}` template includes — those
go through `page_file_name()` too, just with type `view`. Full writeup:
https://emps.ag38.ru/docs/internal-references/

### Response helpers

- `$emps->plaintext_response()` — sets `Content-Type: text/plain`-style
  headers; use for webhook/API endpoints that just echo raw text/status.
- `$emps->json_response($data)` — raw JSON output with headers, no envelope.
- `$emps->json_ok($data = [])` / `$emps->json_error($message, $id = "")` —
  wrap the response in a conventional success/error envelope (check the
  actual JS/Vue consumer or core source for the exact key names before
  relying on the shape — not independently re-verified here).
- `$emps->not_found()` — sends an HTTP 404 + the "not found" template; safe
  to call and `exit;` from inside any module controller once you've decided
  the requested id/slug doesn't exist.
- **Gotcha**: a project module class can define its *own* method also named
  `json_response()` (e.g. `EMPS_Items::json_response()` in this repo) that
  shadows the core `$emps` one *only when called on that object* — check
  which object (`$emps` vs. some local class instance) a `json_response()`
  call is actually going out on before assuming it's the core framework
  method.

## Planned Project Upgrade

Do not implement any upgrades right in this project, I will add another 
project folder for that and direct you to create new modules using EMPS6 instead
of EMPS 4.5.

## Workflow

1. Read EMPS docs above
2. Look at existing similar modules before writing new ones (e.g. look at contests before writing matches)
3. Always call `$emps->db->row_types()` after fetching rows
4. Always `$emps->loadvars()` after generating elinks in a loop
5. Consult before making changes — don't rewrite working code without asking

