# Arcade frontend templates

Requires the supplied Evo Xtreme 2.0.11 template helpers and `evo_status_header()`.
No core helper changes or SQL schema changes are required.

Upload this archive's contents into `modules/Arcade/`, preserving directories.
The supplied module's admin changes, CSS, language strings and score handlers
are preserved. Templates retain the existing frontend layout by default.

## Theme overrides

Copy only the parts you want to change from:

`modules/Arcade/template-parts/`

into:

`themes/YOUR_THEME/template-parts/arcade/`

Keep each filename unchanged. For example:

`themes/Nocturne/template-parts/arcade/arcade-card.php`

Overrides apply only to that theme. Missing parts use the module defaults.
Your existing CMS loader also supports `themes/YOUR_THEME/arcade/` and its
other documented lookup paths. The module folder remains `Arcade` with a
capital A; the theme override subfolder is lowercase `arcade`.

## Available parts

| File | Layout controlled | Main supplied variables |
| --- | --- | --- |
| arcade-page.php | Whole page shell and choice of browser or game | `$game`, all page arguments |
| arcade-header.php | Heading, navigation, notices, login message | `$settings`, `$notice`, `$uid` |
| arcade-browse.php | Game grid, filters and pagination | `$games`, `$categories`, `$allowed` |
| arcade-filters.php | Search and category controls | `$action`, `$q`, `$cid`, `$categories`, `$allowed` |
| arcade-card.php | Individual game card; also used in favourites and hall | `$listed_game`, `$picture`, `$play_url`, `$scores_url`, `$action` |
| arcade-pagination.php | Browse page links | `$count`, `$limit`, `$page`, `$cid`, `$q`, `$action` |
| arcade-game.php | Single game's title, player and related parts | `$game`, all single-game arguments |
| arcade-player.php | Ruffle player and play/restart/fullscreen controls | `$action`, `$player_json`, `$play_url` |
| arcade-favourite.php | Add/remove favourite form | `$uid`, `$favourite`, `$favourite_url` |
| arcade-leaderboard.php | Score list on game pages and hall cards | `$scores` |
| arcade-comments.php | Comments and posting form | `$comments`, `$uid`, `$comment_url` |
| arcade-unavailable.php | Module not yet installed message | No page arguments |

The CMS exposes arguments both as `$args` and extracted local variables.
Pass `$args` when calling nested parts that need the same page data. Keep
nested calls to `get_template_part()` so individual parts remain overridable.
Database queries, category access checks, score sessions and game play-count
updates stay in the controller. Data is fetched before layout rendering.

`$listed_game` contains the original game fields plus `picture_url`, `play_url`,
`scores_url` and `scores` (populated for the hall). Picture URLs are validated
before being passed to the card. Template values are raw unless documented
otherwise: escape text and HTML attributes with `arcade_e()` at output.
Use `arcade_game_title()` before escaping game names to retain the title fix.

## Player and form requirements

Keep `id="arcade-player"` and its escaped `data-player` JSON attribute, and
keep `id="arcade-fullscreen"` on the fullscreen button. The player JavaScript
uses these selectors. Keep CSRF fields (`arcade_csrf()`), POST form methods,
action URLs and the comment field name `message` in custom form layouts.
Templates are PHP code and should only be edited by trusted maintainers.

## Error pages

Missing games and SWFs use `evo_status_header( 404 )`. Restricted game access
uses 403, and unexpected controller or rendering errors use 500. These delegate
to your existing theme-aware `error/404.php`, `error/403.php` and `error/500.php`
templates. The complete frontend render is buffered so an exception can discard
partial markup before the error page is rendered.

Legacy handshake, pnFlashGames and game-hash endpoints retain their protocol
responses and do not render frontend layout parts.

## Shared pagination

The frontend `arcade-pagination.php` part and the admin games list now call the
CMS `get_pagination()` helper. The frontend keeps the configured per-page limit;
the admin uses 50 games per page. Search, category, browse/favourites/hall mode
and the admin `op=arcade` parameter are preserved in page links. Requested page
numbers are clamped before selecting rows so the results match the active page.
Single-page results omit pagination, following the shared helper's behavior.
The standard `pagination-wrap`, `pagination`, `page-item` and `page-link` classes
use the site's shared pagination styling. No copied helper is installed.
If a theme already overrides `arcade-pagination.php`, update or remove that
specific override to use this new shared pagination by default.
