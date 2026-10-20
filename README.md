# Filter Tags

A Question2Answer (Q2A) plugin that lets admins globally hide questions belonging to certain tags,
and gives those tags "owners" who can manage per-tag access and moderate flagged content for their
own tags — in addition to enhancing the core admin "Flagged Posts" moderation page.

## What it does

### 1. Global tag filtering
Questions whose tags match a configured list (set in **Admin → Plugins → Filter Tags**, option
`qa-filtertags-global`, comma-separated) are hidden site-wide from question listings, recent
activity, and tag pages for anyone not otherwise granted access. This is useful for temporarily or
permanently hiding a batch of questions (e.g. an exam/assessment bank) without deleting them.

### 2. Per-tag ownership and per-user access lists
Each global filter tag can have an **owner** (a user id, stored in the `tagmetas` table under the
key `filtertag_owner`). Tag owners — not just full admins — can manage their own tag(s) at
**`/view-filtertags-global`**:
- See stats per tag: question count, "exclusive" questions (only tagged with that filter tag),
  flagged-post count, and how many users currently have access.
- Grant or revoke individual users' access to a tag (stored per-user in `usermetas` under
  `questionaccesstags`), with a handle/email autocomplete search.
- Transfer ownership of a tag to another user (fires a `filtertag_owner_changed` event and emails
  both the old and new owner).
- Admins additionally can rename a tag (optionally migrating it across all users' access lists),
  remove a tag from every user's access list while keeping it in the global list, delete a tag
  outright, or bulk-delete every filter tag that currently has zero questions.

### 3. Flagged-post moderation, scoped by tag or by user
A plugin page at **`/flagged`** lists tags/users that currently have flagged posts:
- **`/flagged/{tag}`** — flagged posts restricted to one global filter tag. Viewable only by an
  admin or that tag's owner.
- **`/flagged/user/{handle}`** — flagged posts authored by one user. Viewable by that user
  themselves or by an admin; also surfaced as a "Flagged" tab on the user's profile sub-navigation.
- Supports the same inline "Clear flags" / "Hide" actions as the core admin flagged-posts page.

### 4. Enhanced core admin "Flagged Posts" page (`admin/flagged`)
This plugin adds a filter toolbar above the core moderation list, letting admins narrow down what's
shown without leaving the page:
- **Visibility** — show all flagged posts, or only ones from "public" questions (i.e. not under any
  global filter tag).
- **Type** — Questions / Answers / Comments / All.
- **Reason** — a specific flag reason (Quality, Spam, Rude, Needs Edit, Duplicate, Copied,
  Prohibited), posts with no reason recorded, or All.
- **Exclude Users** — a searchable checklist of every user who currently has a flagged post, so an
  admin can hide a noisy/known user's posts from the list while triaging everyone else's. The
  excluded-user selection is saved in the browser's `localStorage` (not the server), so it persists
  across visits to the page without needing to reselect it each time; a **Reset** button clears it.

Because core's `qa_db_flagged_post_qs_selectspec()` function does not support Q2A's standard plugin
override hook, this plugin takes over the `admin/flagged` request entirely (see
[qa-filtertags-overrides.php](qa-filtertags-overrides.php), which overrides `qa_page_routing()`) and
serves it from [qa-filtertags-admin-flagged.php](qa-filtertags-admin-flagged.php) — a copy of the
core page that applies the above filters directly to the underlying query, so the filters actually
remove posts from the results (not just adjust the pagination count).

## How it's wired together

| File | Role |
|---|---|
| [qa-plugin.php](qa-plugin.php) | Registers all modules/layers/overrides below with Q2A. |
| [qa-filtertags-admin.php](qa-filtertags-admin.php) | Admin settings form for the global filter-tag list; auto-assigns an owner to newly added tags. |
| [qa-filtertags-overrides.php](qa-filtertags-overrides.php) | Overrides `qa_page_routing()` to redirect `admin/flagged` to this plugin's own page. |
| [qa-filtertags-admin-flagged.php](qa-filtertags-admin-flagged.php) | Replacement for core's `admin/flagged` page; builds on the real, unmodified core selectspec and applies this plugin's filters before the query runs. |
| [qa-filtertags-layer.php](qa-filtertags-layer.php) | Theme layer: renders the filter toolbar (Visibility/Type/Reason/Exclude Users) on `admin/flagged` and `flagged/user/*`, adds the "Flagged" profile sub-nav tab, and recomputes pagination totals when filters are active. |
| [qa-filtertags-flagged-page.php](qa-filtertags-flagged-page.php) | Implements the plugin's own `/flagged`, `/flagged/{tag}` and `/flagged/user/{handle}` pages. |
| [qa-filtertags-manage-page.php](qa-filtertags-manage-page.php) | Implements `/view-filtertags-global` — the tag management/ownership UI. |

## ⚠️ Caution: do not copy `selects.php` over your core file

This folder also contains a `selects.php` left over from an older version of this plugin. **Do not**
copy it over your installation's `qa-include/db/selects.php`, despite any older instructions you may
find referencing this. It is a full, frozen fork of the entire core file from an old Q2A version, not
a minimal patch, and replacing your core file with it can silently remove features added to Q2A
since that fork was made — for example the `queued`/`amaxvote` post columns, `UNHEX()`-based IP
filtering, category/search filters on "my questions/answers/comments" pages, multi-site shared-user
support, and the paginated "users by level" admin page.

The plugin's actual database customizations are applied automatically and safely through
`qa-filtertags-overrides.php` (registered via `qa_register_plugin_overrides`) and the
`qa_page_routing()` takeover described above — replacing the core `selects.php` is **not required**
for any part of this plugin to work. Leave core's `qa-include/db/selects.php` untouched.

## Requirements

- Question2Answer 1.8+ (per `metadata.json`; developed/tested against 1.8.8).
- PHP 5.1.6+ (per `metadata.json`), though modern PHP is recommended.


