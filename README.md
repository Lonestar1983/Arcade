# Evolution Xtreme Arcade

**Module version: 1.0.0 · ArcadeTopUsers block version: 1.0.0**

A standalone Arcade module for Evolution Xtreme, with game categories, browsing and search, favourites, game comments, per-game leaderboards and a top-players block. The player supports SWF games through Ruffle and local HTML5 games through the universal HTML5 player protocol.

## Requirements

- Evolution Xtreme with the template, pagination, user-avatar, option-storage and asset helpers used by this release. The supplied template guide targets Evo Xtreme 2.0.11.
- A working CMS database connection and sessions.
- PHP file uploads enabled for dashboard uploads, with suitable upload/post-size limits.
- Writable `modules/Arcade/games/` and `modules/Arcade/games/pics/` when uploading games and thumbnails.
- A modern browser. SWF playback also needs the Ruffle runtime, which this player currently loads from its CDN; playback depends on Ruffle compatibility with each game.

## Installation

1. Back up your site files and database before replacing an existing installation.
2. Copy the archive's `modules/` and `blocks/` folders into your CMS root, merging the folders. This places the module in `modules/Arcade/` and the folder block in `blocks/ArcadeTopUsers/`.
3. Open Arcade in the administration area (`admin.php?op=arcade`, or your site's renamed admin script).
4. Run the built-in installer when prompted. It creates the four Arcade tables and stores the storage marker in the CMS options.
5. Activate Arcade through your CMS module administration and configure its categories, access, title, announcement and page size.
6. Add game files and images through the Arcade dashboard. The bundled default catalogue references game assets; this archive does not include those game binaries or thumbnail images.
7. Add and enable the ArcadeTopUsers folder block through the CMS Blocks Manager and choose its position.

The installer creates tables if absent and imports the default catalogue only when the content table is empty. It is a fresh-install routine, not an old Arcade migration tool. `storage_version = 2` identifies the database format; it is separate from the release version and has not been changed by this update. Existing data is not reset by these version-define changes.

## Version constants and assets

| Component | Constant | Defined in | Value |
| --- | --- | --- | --- |
| Module | `EVO_ARCADE_VERSION` | `modules/Arcade/includes/functions.php` | `1.0.0` |
| Block | `EVO_ARCADE_TOP_USERS_VERSION` | `blocks/ArcadeTopUsers/index.php` | `1.0.0` |

Both constants are guarded against redefinition. The module's frontend/admin CSS and player JavaScript use `EVO_ARCADE_VERSION` instead of independent hard-coded asset versions. The block's JavaScript uses `EVO_ARCADE_TOP_USERS_VERSION`. Block CSS remains loaded by the existing CMS folder-block loader, as in the supplied block; its cache-version handling belongs to that loader.

When releasing matching module and block updates, increment both constants to the same release number. They are deliberately defined independently so the folder block can show its unavailable message even if the module helpers are missing. These constants do not install an updater, create a version.json manifest or run database upgrades automatically.

## Game administration

The dashboard supports game/category maintenance, SWF or HTML uploads, optional thumbnail uploads, automatic detection with a manual-edit fallback, dimensions, scoring options and moderation. Check the detected dimensions and protocol before saving. Images are stored under `games/pics/`; game files are stored under `games/`.

For HTML5 games, a self-contained `.html` file is the simplest upload format. If a game uses separate assets, deploy those assets with their expected paths. Uploading an HTML file alone does not bundle its dependencies.

## HTML5 score integration

HTML5 games must live under the same site's `modules/Arcade/games/` directory. The player creates a sandboxed iframe and accepts messages only from that game frame on the site's origin. Games announce readiness with `arcade:ready`; the player replies with `arcade:init` when authenticated score submission is available.

```javascript
const arcadeOrigin = window.location.origin;
window.parent.postMessage({
    type: 'arcade:ready',
    version: 1,
    width: 900,
    height: 700,
    restartMode: 'session'
}, arcadeOrigin);

window.addEventListener('message', function (event) {
    if (event.source !== window.parent || event.origin !== arcadeOrigin) return;
    if (event.data && event.data.type === 'arcade:init') {
        // Enable a submit control when event.data.capabilities.submit is true.
    }
});
```

When the player has announced submission support, send `arcade:submit` with a positive safe-integer `score` at the appropriate game-over/submit action. Use the HTML5/IBPro scoring configuration (type 7). The parent player supplies the authenticated run token and posts the score to the module; games must not invent tokens. For replay, send `arcade:restart` and wait for `arcade:restarted` before starting the next scored run. Handle `arcade:restart-error` by offering a reload. No game-name-specific player.js edits are required for games using this protocol. Standalone games should remain playable when no Arcade parent is present.

## ArcadeTopUsers block

The block ranks up to five players by **submitted play totals**, using the plays recorded in Arcade score rows. It is not a ranking of total points or every launch of a game. Only active games in categories accessible to the viewer contribute. Players are displayed individually with avatars, profile links and previous/next navigation when multiple entries exist. The block shows an empty/unavailable message when appropriate.

## Data and settings

The module uses four prefixed tables: `arcade_content`, `arcade_scores`, `arcade_favourites` and `arcade_runs` (each preceded by your CMS database prefix and an underscore). Content stores game, category and comment records. Scores store each player's game score and submitted plays; favourites store saved games; runs store score-session tokens. Settings are stored through the existing `arcade_settings` CMS option. Saving settings invalidates the existing CMS configuration cache.

## Theme templates and overrides

Requires the supplied Evo Xtreme 2.0.11 template helpers and `evo_status_header()`.
No core helper changes or SQL schema changes are required.

The module files belong in `modules/Arcade/`, preserving directories.
The supplied module's admin changes, CSS, language strings and score handlers
are preserved. Templates retain the existing frontend layout by default.

### Theme overrides

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

### Available parts

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

### Player and form requirements

Keep `id="arcade-player"` and its escaped `data-player` JSON attribute, and
keep `id="arcade-fullscreen"` on the fullscreen button. The player JavaScript
uses these selectors. Keep CSRF fields (`arcade_csrf()`), POST form methods,
action URLs and the comment field name `message` in custom form layouts.
Templates are PHP code and should only be edited by trusted maintainers.

### Error pages

Missing games and SWFs use `evo_status_header( 404 )`. Restricted game access
uses 403, and unexpected controller or rendering errors use 500. These delegate
to your existing theme-aware `error/404.php`, `error/403.php` and `error/500.php`
templates. The complete frontend render is buffered so an exception can discard
partial markup before the error page is rendered.

Legacy handshake, pnFlashGames and game-hash endpoints retain their protocol
responses and do not render frontend layout parts.

### Shared pagination

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


## Troubleshooting

- **Installer keeps appearing:** verify all four tables exist and the stored `arcade_settings` option has `storage_version` set to 2. Do not repeatedly reinstall over incompatible old tables.
- **Blank/missing game:** check its stored file path, uploaded file, category permissions and browser console. SWF support varies by game.
- **HTML5 score does not save:** verify login, type 7, same-origin game location, readiness/init messages, a positive integer score and a fresh run after replay.
- **Old CSS or JavaScript:** increment the applicable release constants together for a new release, then refresh the browser. Block CSS also follows the CMS folder-block loader's cache policy.
- **Theme changes do not appear:** check the active theme's override directory and exact lowercase `arcade` subfolder.
- **Ruffle assets folder:** the existing `assets/ruffle/README.txt` describes optional self-hosted assets, but the supplied player currently loads Ruffle from its CDN. Adding files to that folder alone does not change the runtime URL.

## Documentation

The complete supplied template guide is incorporated above. `modules/Arcade/TEMPLATES.md` is retained as a standalone reference. This package adds matching version defines and documentation without changing the database schema or game-score behaviour.
