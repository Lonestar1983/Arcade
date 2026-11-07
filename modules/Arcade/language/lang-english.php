<?php

defined( 'NUKE_EVO' ) || exit;

global $lang_new;

$lang_new['arcade'] = array(
    'title'                => 'Arcade',
    'url_label'            => 'Arcade dashboard',
    'all_games'            => 'All games',
    'favourites'           => 'My favourites',
    'search'               => 'Search games',
    'sort_by'              => 'Sort by',
    'sort_default'         => 'Default order',
    'sort_plays_asc'       => 'Plays: lowest first',
    'sort_plays_desc'      => 'Plays: highest first',
    'sort_score_asc'       => 'Highest score: lowest first',
    'sort_score_desc'      => 'Highest score: highest first',
    'play'                 => 'Play game',
    'play_short'           => 'Play',
    'leaderboard'          => 'Leaderboard',
    'save'                 => 'Save',
    'delete'               => 'Delete',
    'edit'                 => 'Edit',
    'add_game'             => 'Add game',
    'categories'           => 'Categories',
    'settings'             => 'Settings',
    'games'                => 'Games',
    'install'              => 'Install Arcade tables',
    'not_installed'        => 'The Arcade storage needs installation or an upgrade. Ask an administrator to run the Arcade setup.',
    'empty'                => 'No games found.',
    'login'                => 'Sign in to save scores and favourites.',
    'invalid_request'      => 'This request could not be verified. Reload the page and try again.',
    'forbidden'            => 'You do not have access to this game.',
    'missing'              => 'The requested game was not found.',

    // @translator Keep the runtime name Ruffle unchanged.
    'ruffle_missing'       => 'Ruffle could not be loaded. Please contact the administrator.',

    'ruffle_error'         => 'The game could not be loaded. Please try again or contact the administrator.',
    'game_size'            => 'Game size',
    'game_size_fit'        => 'Fit screen',
    'game_size_larger'     => 'Larger (fill width)',
    'game_size_original'   => 'Original size',
    'fullscreen'           => 'Full screen',
    'add_favourite'        => 'Add to favourites',
    'remove_favourite'     => 'Remove favourite',
    'comments'             => 'Comments',
    'comment'              => 'Your comment',
    'post_comment'         => 'Post comment',
    'unknown_user'         => 'Former member',
    'score_saved'          => 'Your score has been saved.',
    'score_invalid'        => 'The score could not be verified. Start the game again from the Arcade.',
    'admin'                => 'Arcade administration',
    'name'                 => 'Name',
    'description'          => 'Description',

    // @translator Keep the file format abbreviation SWF unchanged.
    'swf'                  => 'Game filename (SWF or HTML)',

    'picture'              => 'Thumbnail filename',
    'width'                => 'Width',
    'height'               => 'Height',

    // @translator Refers to the variable name the game uses to submit its score.
    'scorevar'             => 'Score variable',

    // @translator Keep GFARV2 and Airport unchanged. Preserve the protocol numbers 3, 4/5 and 6 and their associations.
    'type'                 => 'Game protocol (3: GFARV2, 4/5: legacy, 6: Airport session protocol)',

    'category'             => 'Category',
    'active'               => 'Enabled',
    'order'                => 'Display order',

    // @translator Preserve the access codes: 0 means everyone, 1 means members and 2 means Arcade administrators.
    'access'               => 'Access (0: everyone, 1: members, 2: Arcade administrators)',

    'announcement'         => 'Announcement',
    'per_page'             => 'Games per page',
    'saved'                => 'Changes saved.',
    'install_done'         => 'Arcade tables are ready.',
    'confirm_delete'       => 'Confirm deletion',
    'cancel'               => 'Cancel',
    'storage_install_help' => 'Create the Arcade tables and install the default game catalogue.',
    'storage_install'      => 'Install or upgrade Arcade storage',
    'maintenance'          => 'Arcade maintenance',
    'reset_all_plays'      => 'Reset all play counts',
    'reset_all_plays_help' => 'Sets every game play count and each player score play count to zero. Best scores, games and favourites are kept.',
    'reset_all_plays_done' => 'All game and player play counts have been reset.',
    'reset_all_scores'     => 'Clear all scores',
    'reset_all_scores_help' => 'Permanently removes every player score from every game and cancels active score submissions. Game play counts, games and favourites are kept. Players must reopen their games to submit new scores.',
    'reset_all_scores_done' => 'All scores have been cleared. Players must reopen active games before submitting scores.',
    'reset_acknowledge'    => 'I understand this resets data across every game and cannot be undone.',
    'reset_scores'         => 'Reset game scores',
    'files_help'           => 'Upload a game and its thumbnail below, or enter filenames for files already on the server.',
    'database_error'       => 'An Arcade database operation failed. Check the server error log.',
    'invalid_fields'       => 'Check the filenames, category, game type and required fields.',

    // @translator Keep the names Ruffle and unpkg unchanged. CDN means content delivery network.
    'ruffle_setup'         => 'Ruffle loads automatically from the unpkg CDN. No separate runtime upload is required.',

    // @translator Keep the file format abbreviation SWF unchanged.
    'upload_swf'           => 'Upload game (SWF or HTML)',

    'upload_picture'       => 'Upload game thumbnail',

    // @translator Keep SWF, GIF, PNG, JPG and WebP unchanged. Preserve the limits: games 32 MB, images 5 MB and 8192 pixels per side. MB means megabytes.
    'upload_help'          => 'Upload a SWF, HTML or HTM game (up to 32 MB), and an optional thumbnail (GIF, PNG, JPG or WebP, up to 5 MB and 8192 pixels per side). Uploads take priority over existing filenames. Existing files are never overwritten. Server upload limits also apply.',

    'upload_invalid'       => 'The uploaded file could not be verified.',

    // @translator Keep SWF unchanged. The original filename must remain unchanged; this is an instruction to the uploader.
    'upload_filename'      => 'Use filenames containing only letters, numbers, hyphens and underscores, followed by an allowed extension. Keep the original SWF filename.',

    'upload_failed'        => 'The upload failed. Check the server upload limits and folder permissions, then select the files again.',

    // @translator Keep SWF unchanged. Preserve the limits: games 32 MB and images 5 MB. MB means megabytes.
    'upload_size'          => 'The file is empty or too large. Game files may be up to 32 MB; images may be up to 5 MB.',

    // @translator Keep GIF, PNG, JPG and WebP unchanged. Preserve the limit of 8192 pixels per side.
    'upload_image_invalid' => 'Choose a valid GIF, PNG, JPG or WebP image matching its extension, no larger than 8192 pixels per side.',

    // @translator Keep SWF unchanged. "Header" means the identifying data inside the file.
    'upload_swf_invalid'   => 'The selected file does not have a valid SWF header.',

    'upload_directory'     => 'The game upload folder could not be created. Check its permissions.',
    'upload_exists'        => 'The destination file could not be created. If that filename already exists, enter it in the filename field instead of uploading it again; otherwise check folder permissions.',
    'protocol_label'       => 'Score submission format',

    // @translator Keep SWF unchanged. "Automatic" names the automatic scoring-format detection option.
    'protocol_0'           => 'Automatic — inspect SWF when saved',

    // @translator Keep the protocol identifier GFARV2 and type number 3 unchanged.
    'protocol_3'           => 'GFARV2 (type 3)',

    // @translator Keep the score identifier vscore and type number 4 unchanged.
    'protocol_4'           => 'Legacy vscore (type 4)',

    // @translator Keep the score identifier vscore and type number 5 unchanged.
    'protocol_5'           => 'Legacy vscore (type 5)',

    // @translator "Session handshake" means the initial exchange used to establish a game session. Preserve type number 6.
    'protocol_6'           => 'Session handshake (type 6)',

    // @translator Keep IBPro, the score identifier newscore and type number 7 unchanged.
    'protocol_8' => 'pnFlashGames (type 8)',
    'protocol_7'           => 'IBPro newscore (type 7)',

    // @translator Keep SWF unchanged. Translate "Automatic" consistently with the protocol_0 option.
    'detect_help'          => 'Leave scoring on Automatic for SWF detection. Choose a manual protocol and score variable only when needed. HTML5 games use IBPro and gscore through the universal player; they must implement its score bridge to submit scores.',

    // @translator Keep the technical abbreviations SWF and LZMA unchanged.
    'detect_unknown'       => 'The SWF could not be identified automatically. Choose its format and score variable under Advanced settings. Unknown, ambiguous and LZMA-compressed games require manual settings.',

    // %1$s: Detected scoring protocol or format name.
    // %2$s: Score variable identifier used by the game.
    'detect_done'          => 'Detected: %1$s. Score variable: %2$s.',

    'restart'              => 'Restart game',
    'plays'                => 'Plays',
    'highest_score'        => 'Highest score',
    'hall'                 => 'Hall of fame',
    'no_score'             => 'No scores yet.',
    'custom_title'         => 'Game title (optional)',

    // @translator Keep SWF unchanged.
    'custom_title_help'    => 'Leave blank to generate a title from the SWF filename.',

    'automatic_install'    => 'Automatic installation',

    // @translator Keep SWF unchanged.
    'automatic_help'       => 'Upload the SWF and thumbnail to install with detected scoring settings. Enter an optional custom title, or leave it blank to use the SWF filename; the default category is used and the game is enabled. You can edit these afterwards. If detection fails, review the prefilled manual form below.',

    'manual_install'       => 'Manual installation',
    'manual_help'          => 'Upload files first, or use existing server filenames below. Set the game details and scoring format here. Editing uses this same form and keeps existing files unless replacements are uploaded.',
    'existing_files'       => 'Alternative: use files already on the server',

    // @translator Keep SWF and the paths modules/Arcade/games/ and modules/Arcade/games/pics/ unchanged.
    'existing_help'        => 'Already uploaded the files? Enter their filenames below. To switch an existing game from Flash to HTML5, change the game filename to its HTML file. Keep the same game entry to retain its scores and favourites.',

    'scoring_settings'     => 'Scoring settings',

    // @translator Keep SWF and HTML5 unchanged. Preserve the numeric range 1–16384.
    'dimensions_help'      => 'Leave dimensions blank or 0 to detect SWF dimensions automatically, keep the current size, or use HTML5 defaults. HTML5 games can report their size to the universal player. Enter a width or height only to override it (1–16384 pixels).',

    'detect_manual'        => 'The SWF scoring format could not be identified. Your uploaded files have been kept and the form is filled in. Open Advanced settings, select the scoring protocol and variable, then save again without re-uploading.',

    // @translator Keep SWF unchanged.
    'auto_files_required'  => 'Automatic installation requires both a SWF and a thumbnail. Select both, or use the manual form with existing files.',

    'disabled'             => 'Disabled',
    'uncategorised'        => 'Uncategorised',
    'edit_game'            => 'Edit game',
    'game_form_help'       => 'Upload a game or enter an existing filename, then save. The title can be left blank for new games to use the filename. SWF settings are detected automatically; HTML5 games use the universal player.',
    'advanced_settings'    => 'Advanced settings',
    'width_optional'       => 'Width override (optional)',
    'height_optional'      => 'Height override (optional)',
    'upload_html_invalid'  => 'Choose a complete HTML or HTM document without binary data or PHP tags.',
);