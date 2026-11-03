# EMPS6 — Portable Project Instructions for Claude

> **Project-specific notes.** This emps-factory project still runs EMPS4.5,
> but I would like to later upgrade it to EMPS6. So far all I need is to
> make sture that it runs on PHP 8.2 and above and has no security vulnerabilities.

This file is meant to be **copied as-is into any EMPS6 project** — it holds
framework-general knowledge that applies regardless of which specific site
runs on EMPS6. It should contain nothing specific to one project (no
project name, purpose, module map, repo layout, credentials, etc.) — that
kind of information belongs in that project's own `README.md`, which this
file assumes exists and should be read alongside it.

**Maintenance note:** this file is expected to evolve. Whenever working on
an EMPS6 project surfaces a framework-general gotcha, pattern, or correction
that isn't obvious from the docs and would help on the *next* EMPS6 project
too, add it to the "EMPS6 Findings" section below (not to the project's
README) — the whole point is that copying this one file into a new project
should make Claude productive there immediately, without re-learning things
the hard way each time. Keep entries framework-general; if something is true
only of one project, it belongs in that project's README instead.

## Stack (typical, confirm per-project in README.md)

PHP + **EMPS6** framework, MySQL (MyISAM), Smarty templates, Vue 2 (Options
API, no build step), Bulma CSS is a common but not universal choice. Exact
PHP version, DB details, and any project-specific frontend stack live in the
project's `README.md`.

The EMPS6 framework core is normally **not vendored in the project repo** —
it's expected to be available on the server's PHP `include_path` (see the
project's `htdocs/index.php`, typically
`require_once "EMPS6/{version}/emps_bootstrap.php";`, resolved via
`include_path`, not a relative file in the repo).

## EMPS6 Documentation — READ THESE FIRST

The EMPS6 repository (https://github.com/AlexGnatko/EMPS6) contains
documentation written specifically for AI coding agents. Read these before
writing any code in an EMPS6 project:

- https://raw.githubusercontent.com/AlexGnatko/EMPS6/main/Claude/EMPS-core.md —
  routing, module files, URL helpers, JSON responses, SQLSync, MJS, enums,
  page properties, auth. **Primary reference for how URLs/modules work.**
- https://raw.githubusercontent.com/AlexGnatko/EMPS6/main/Claude/EMPS-patterns.md —
  admin module pattern (PHP+JS+Smarty skeleton), key gotchas, useful
  `$emps->db` methods, `EMPS_Service`/`EMPS_Heartbeat` for periodic tasks, the
  `{module}.class.php` domain-class pattern.
- https://raw.githubusercontent.com/AlexGnatko/EMPS6/main/bootcamp/App-Design.md —
  *why* EMPS has no build step, no separate front/back-end repos, MySQL/MyISAM
  choice.
- https://raw.githubusercontent.com/AlexGnatko/EMPS6/main/bootcamp/Vue.md —
  how Vue is used without a build step (Options API only, no SFCs,
  `EMPS.vue_component_direct` vs `Vue.component`, mixins over components, MJS
  component organization).
- General docs website: https://emps.ag38.ru/docs/ (be skeptical — see
  "Be skeptical of the EMPS docs site itself" below).

These are more authoritative than anything hand-derived for a project — if
something in a project's README conflicts with them, trust the EMPS6 docs
above (and flag the conflict rather than silently picking one).

## Key routing facts (recap — see EMPS-core.md for full detail)

- URL vars in order: `pp, key, start, ss, sd, sk, sm, sx, sy`.
- Only `$pp` (first URL segment) picks the module folder — hyphens in `$pp`
  become slashes, e.g. `/manage-orders/` → `modules/manage/orders/orders.php`.
- `$start` is **pagination offset**, not an item id (common mistake).
- `key` can be a slug+id, e.g. `/pool/5-football-world-cup/` → `$key =
  "5-football-world-cup"`, `intval($key)` = 5.
- Always `$emps->loadvars()` after building `elink()`s in a loop.
- Always `$emps->db->row_types($table, $row)` after `fetch_named()`/`get_row()`.

## Workflow

1. Read the EMPS6 Claude docs above before writing code.
2. Read the project's own `README.md` for what the project actually is,
   its stack specifics, repo/module layout, and any project-specific
   decisions or open questions — don't assume this file alone is enough
   context to start working.
3. Consult before making structural/architectural decisions that are
   expensive to change later — don't guess silently.
4. If this file conflicts with a project's README, or with the EMPS6 docs
   linked above, say so rather than silently picking one.
5. **Never create git commits (or push) on your own initiative.** Committing
   is the project owner's job — stage/prepare changes if useful, but leave
   the actual `git commit`/`git push` to them, even if they haven't
   explicitly said "don't commit" in a given session. Only commit if
   explicitly asked to, in that session.

## EMPS6 Findings (framework-general — applies to any EMPS6 project)

Lessons learned working with EMPS6 that aren't obvious from a first read of
the docs at https://emps.ag38.ru/docs/.

### SQLSync tables are not actually temporary

`modules/{module}/sql/module.sql` files are written as
`CREATE TEMPORARY TABLE temp_xxx (...)`, but this is NOT a staging/scratch
table — it's the **declarative live schema**. Visiting `/sqlsync/{module}/`
strips the `temp_` prefix (replacing it with the real table prefix), drops
`TEMPORARY`, and creates the table if missing or `ALTER TABLE`s it into
matching shape if it already exists. It's idempotent — re-run any time after
editing the SQL file. See https://emps.ag38.ru/docs/sqlsync/.

### Name your main text column `name`, not `title`/`label`

`<selector>`/`<descriptor>` in entity-search mode (`type="table_name"
:search="true"`) are backed by `modules/pick/ng/` (`EMPS_NG_PickList` /
`EMPS_NG_PickDescribe` core classes, extended per-project as
`EMPS_Local_PickList` / `EMPS_Local_PickDescribe`). **By default they
search/display a column literally called `name`.** If a table's main text
column is called anything else, the picker silently returns nothing (or a
blank label) — no error. Fix: either name the column `name` up front, or add
a per-table `else if ($this->table_name == "...")` case to both
`modules/pick/ng/list/project.php` and `modules/pick/ng/describe/project.php`.
Prefer naming it `name` — it's free, the override isn't.

### `<selector>` is not for enums

There is no built-in "enum mode" for `<selector>` — `type` always resolves
through `pick/ng`, which is for picking a database row by id. For a
status/type field backed by `enum.nn.txt`, use a plain
`<select v-model="row.status">` with `v-for="item in enums['x']"`, populated
via `EMPS.vue_load_enums(this, "x")` and displayed with `enum_val('x', code)`
(from `common_mixin`, `/mjs/comp-mixins/common.js`).

### Table column conventions

- `id` — `bigint NOT NULL AUTO_INCREMENT`, primary key.
- `cdt` / `dt` — created / last-modified timestamps as `bigint` **Unix
  timestamps**, not SQL `DATETIME`.
- `status` (or a specific name like `pub`) — an int/bigint code paired with
  an entry in `enum.nn.txt`, usually with a matching `..._class` enum
  mapping each code to a Bulma color class (`is-success`, `is-dark`, etc.)
  for badges/tags.
- `<other_table>_id` — foreign keys named after the target table, singular,
  + `_id` (e.g. `author_id`). This is what `<selector>`/`<descriptor>` and
  hand-written joins expect.

### Smarty + Vue share `{{ }}` delimiters — the real rule is the space, not the keyword

The actual rule is purely lexical: **`{{` immediately followed by a
non-space character is a Smarty tag, parsed on the server. `{{` followed by
a space is left untouched for Vue** (e.g. `{{$product.name}}` → Smarty;
`{{ modal_item.name }}` → Vue, note the space). Bare Vue-only expressions —
anything referencing plain JS/Vue data with no `$`-prefixed Smarty
variable — MUST have that leading space, or Smarty tries to parse them as
its own tag and throws a template error the moment the expression contains
something Smarty's grammar doesn't expect (e.g. `{{modal_item.name}}` fails
server-side with `Unexpected "."`, while `{{ modal_item.name }}` renders
fine and is left for Vue).

Separately: any `{{script ...}}` / `{{include file="db:..."}}` component-
registration tags in a `.nn.htm` file must sit OUTSIDE whatever `<div>` Vue
mounts to (its `id="..."` element) — Vue can't tolerate `<script>`/template
tags inside its mount element. When writing *documentation* `.md` files that
go through the same Smarty pipeline, wrap any code sample containing literal
`{{`/`}}` in `{{literal}}...{{/literal}}` or Smarty will try to parse it and
break the page.

### Be skeptical of the EMPS docs site itself

`emps.ag38.ru/docs/` is hand/AI-maintained and can contain stale or
never-actually-tested claims. If something documented there doesn't match
observed behavior, say so and ask before propagating it further, rather than
trusting the docs as ground truth.

### Core ships working default site-shell templates — a fresh project doesn't need its own

`{{include file="db:page/head"}}`/`db:page/foot"` (invoked by EMPS on every
page) in turn include `db:site/head` / `db:site/foot` / `db:site/styles` /
`db:site/headscripts` / `db:site/footscripts`. The **core install** (outside
any project repo, on the server's include path) already ships working
fallbacks for all of these. Several *other* core-referenced includes
(`db:inc/google`, `db:page/commonfoot`, `db:page/mainhead`,
`db:html_tag_extra`, ...) don't exist as physical files anywhere in the core
repo either, meaning EMPS's `db:` Smarty include resolution must no-op
silently on a missing template rather than fatal-erroring.

**Don't override `site/styles.nn.htm` just to add a project stylesheet.**
The core's own default `site/styles.nn.htm` already links `/css/default.css`,
`/css/emps.css`, `/css/site-default.css`, `/css/design.css`, and
`/css/media.css` — missing ones just 404 harmlessly. So the right move for
adding project CSS without touching any template is simply to create/edit
one of those five filenames under the project's `htdocs/css/` and let the
core default pick it up. Only override `templates/nn/site/*` files when you
need genuinely different *markup* (e.g. a custom nav/footer shell), not just
to point at a differently-named stylesheet.

### Config scripts (predisplay.php, webinit.php, ...) run inside a function — declare URL vars `global`

Scripts under `modules/_common/config/project/` (`predisplay.php`, `webinit.php`,
`actions.php`, ...) are `include`d from inside a function, so they run in that
**function's scope, not the global scope**. They only see the variables the
including function declared `global` itself: `$emps` (declared in
`$emps->pre_display`) and `$smarty` (declared in the core
`6.5/common/config/predisplay.php`) work for that reason. Everything else — the URL
variables `$pp`, `$key`, `$ss`, `$start` and objects such as `$cli`, `$items` — is
*not* visible unless the script declares it. Without `global`, `$pp` is simply
`null` — no error, the condition silently never matches (e.g. `page_property("my", 1)`
for `my-*` pages never fired).

Always start such a script with `global $pp, $key, $ss, $start;` (plus whatever else
it uses; adding `$emps, $smarty` too is harmless and makes the script self-documenting).
An unused `global` is harmless; a missing one is a silent bug. Same habit for module
controllers that touch these variables inside closures or helper functions.

### Bulma 1.0: no `columns is-variable` — use `columns is-N`

In Bulma 1.0 the column gap is set directly on `.columns` (`is-0` … `is-8`, via `--bulma-column-gap`);
`is-variable` no longer exists. The legacy `.columns.is-variable` rule shipped in EMPS core's `ext.css`
still reads the old `--columnGap` variable, which `is-2` etc. no longer set, so `columns is-variable is-2`
renders **gapless** (padding resolves to 0). Write `<div class="columns is-2">` instead.
