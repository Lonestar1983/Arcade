<?php
/**
 * Arcade administration controller and form rendering.
 *
 * Handles installation, settings, categories, game uploads,
 * automatic score-protocol detection, manual game editing and moderation.
 * Administrator POST requests are checked for a valid CSRF token.
 * Uploaded files are removed after failed saves, except when detection falls
 * back to the manual form so the administrator can finish installation.
 *
 * Requires the Evo administration bootstrap and Arcade helper files.
 *
 * @global string $admin_file Administration script basename.
 *
 * @package Arcade
 * @version 1.0.0
 */

defined( 'ADMIN_FILE' ) || exit ();

require_once dirname( __DIR__ ) . '/includes/functions.php';
require_once dirname( __DIR__ ) . '/includes/language-loader.php';
require_once dirname( __DIR__ ) . '/includes/install.php';
require_once dirname( __DIR__ ) . '/includes/uploads.php';
require_once dirname( __DIR__ ) . '/includes/detect-game.php';

if ( ! is_mod_admin( 'Arcade' ) ) {
    http_response_code( 403 );
    exit ( arcade_e( arcade_t( 'forbidden' ) ) );
}

arcade_session();

evo_include_style( 'evo-arcade', 'modules/Arcade/assets/css/arcade.css', EVO_ARCADE_VERSION );

global $admin_file;

$admin_url         = $admin_file . '.php?op=arcade';
$message           = '';
$uploaded_files    = array();
$detected_protocol = array();
$game_form         = null;
$manual_open       = false;

// Process authorised administrator actions before rendering the page.
try {
    if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        arcade_check_csrf();

        $task = arcade_request( 'task' );
        $id   = max( 0, (int) arcade_request( 'id' ) );

        if ( $task === 'install' ) {
            arcade_install();
            $message = arcade_t( 'install_done' );
        } elseif ( arcade_installed() ) {
            if ( $task === 'settings' ) {
                $new_settings = arcade_settings();
                foreach ( array( 'title', 'announcement', 'per_page' ) as $key ) {
                    $value = arcade_request( $key );

                    if ( $key === 'per_page' ) {
                        $value = (string) max( 1, min( 60, (int) $value ) );
                    } else {
                        $value = substr( $value, 0, $key === 'title' ? 100 : 2000 );
                    }

                    $new_settings[ $key ] = $value;
                }
                arcade_save_settings( $new_settings );

                $message = arcade_t( 'saved' );
            } elseif ( $task === 'category' ) {
                $name     = trim( substr( arcade_request( 'name' ), 0, 100 ) );
                $access   = max( 0, min( 2, (int) arcade_request( 'access' ) ) );
                $position = (int) arcade_request( 'position' );

                if ( $name === '' ) {
                    throw new InvalidArgumentException( arcade_t( 'invalid_fields' ) );
                }
                if ( $id ) {
                    arcade_db(
                        'UPDATE ' . arcade_table( 'content' ) . ' SET name=' . arcade_quote( $name ) .
                            ',access=' . $access . ',position=' . $position . " WHERE record_type='category' AND id=" . $id
                    );
                } else {
                    arcade_db(
                        'INSERT INTO ' . arcade_table( 'content' ) . " (record_type,name,access,position) VALUES('category'," .
                            arcade_quote( $name ) . ',' . $access . ',' . $position . ')'
                    );
                }
                $message = arcade_t( 'saved' );
            } elseif ( $task === 'game' ) {
                $new_game = $id === 0;
                $existing  = $id
                    ? arcade_one( 'SELECT * FROM ' . arcade_table( 'content' ) . " WHERE record_type='game' AND id=" . $id )
                    : null;

                if ( $id && ! $existing ) {
                    throw new InvalidArgumentException( arcade_t( 'missing' ) );
                }

                $game_form = array(
                    'id'          => $id,
                    'name'        => trim( substr( arcade_game_title( arcade_request( 'name' ) ), 0, 100 ) ),
                    'description' => substr( arcade_request( 'description' ), 0, 4000 ),
                    'swf'         => arcade_request( 'swf' ),
                    'picture'     => arcade_request( 'picture' ),
                    'category_id' => (int) arcade_request( 'category_id', '1' ),
                    'type'        => (int) arcade_request( 'type', '0' ),
                    'scorevar'    => arcade_request( 'scorevar', 'vscore' ),
                    'position'    => (int) arcade_request( 'position' ),
                    'active'      => isset( $_POST['active'] ) ? 1 : 0
                );

                $manual_open    = true;
                $upload_result  = arcade_admin_uploads();
                $uploaded_files = $upload_result['created'];

                foreach ( array( 'swf', 'picture' ) as $field ) {
                    if ( isset( $upload_result['uploads'][$field] ) ) {
                        $game_form[$field] = $upload_result['uploads'][$field]['name'];
                    }
                }

                if ( $new_game && $game_form['name'] === '' ) {
                    $game_form['name'] = substr(
                        ucwords( str_replace( array( '_', '-' ), ' ', pathinfo( $game_form['swf'], PATHINFO_FILENAME ) ) ),
                        0,
                        100
                    );
                }

                if (
                    $game_form['name'] === '' ||
                    ! arcade_file( $game_form['swf'] ) ||
                    ( $game_form['picture'] !== '' && ! arcade_file( $game_form['picture'], true ) ) ||
                    ! arcade_category( $game_form['category_id'] ) ||
                    ! in_array( $game_form['type'], array( 0, 3, 4, 5, 6, 7, 8 ), true )
                ) {
                    throw new InvalidArgumentException( arcade_t( 'invalid_fields' ) );
                }

                $swf_path = dirname( __DIR__ ) . '/games/' . $game_form['swf'];

                $html_game = preg_match( '/\.html?$/iD', $game_form['swf'] ) === 1;

                // HTML5 games use the universal player's token-bound IBPro bridge.
                if ( $html_game ) {
                    $game_form['type']     = 7;
                    $game_form['scorevar'] = 'gscore';
                } elseif ( $game_form['type'] === 0 ) {
                    $detected_protocol = arcade_detect_game( $swf_path, $game_form['scorevar'] );
                    if ( ! $detected_protocol ) {
                        // Keep validated uploads for the prefilled manual form; no game is inserted yet.
                        $uploaded_files = array();
                        throw new InvalidArgumentException( arcade_t( 'detect_manual' ) );
                    }
                    $game_form['type']     = $detected_protocol['type'];
                    $game_form['scorevar'] = $detected_protocol['scorevar'];
                }

                if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/D', $game_form['scorevar'] ) ) {
                    throw new InvalidArgumentException( arcade_t( 'invalid_fields' ) );
                }

                $dimensions = $html_game ? array() : arcade_swf_dimensions( $swf_path );
                $values     = $game_form;
                unset( $values['id'] );

                $same_file = $existing && $existing['swf'] === $game_form['swf'];
                foreach ( array( 'width' => 0, 'height' => 1 ) as $dimension => $index ) {
                    $override = (int) arcade_request( $dimension );
                    if ( $override < 0 || $override > 16384 ) {
                        throw new InvalidArgumentException( arcade_t( 'invalid_fields' ) );
                    }
                    $fallback = $same_file ? (int) $existing[$dimension] : ( $html_game
                        ? ( $dimension === 'width' ? 660 : 960 )
                        : ( $dimension === 'width' ? 550 : 380 ) );
                    $values[$dimension] = $override ?: ( $dimensions[$index] ?? $fallback );
                }
                $assign           = array();

                foreach ( $values as $key => $value ) {
                    $assign[] = '`' . $key . '`=' . arcade_quote( $value );
                }

                arcade_db(
                    $id
                        ? 'UPDATE ' . arcade_table( 'content' ) . ' SET ' . implode( ',', $assign ) .
                            " WHERE record_type='game' AND id=" . $id
                        : 'INSERT INTO ' . arcade_table( 'content' ) . " SET record_type='game'," . implode( ',', $assign )
                );

                $uploaded_files = array();
                $game_form      = null;
                $manual_open    = false;
                $message        = arcade_t( 'saved' );

                if ( $detected_protocol ) {
                    $message .=
                        ' ' . sprintf(
                            arcade_t( 'detect_done' ),
                            arcade_t( 'protocol_' . $values['type'] ),
                            $values['scorevar']
                        );
                }
            } elseif ( in_array( $task, array( 'reset_all_plays', 'reset_all_scores' ), true ) ) {
                // Global maintenance requires explicit confirmation as well as CSRF validation.
                if ( arcade_request( 'confirmed' ) !== '1' || arcade_request( 'reset_acknowledged' ) !== '1' ) {
                    throw new InvalidArgumentException( arcade_t( 'invalid_request' ) );
                }

                arcade_db( 'START TRANSACTION' );
                try {
                    if ( $task === 'reset_all_plays' ) {
                        arcade_db( 'UPDATE ' . arcade_table( 'content' ) . " SET plays=0 WHERE record_type='game'" );
                        arcade_db( 'UPDATE ' . arcade_table( 'scores' ) . ' SET plays=0' );
                    } else {
                        arcade_db( 'DELETE FROM ' . arcade_table( 'scores' ) );
                        // Invalidate active runs so pre-reset sessions cannot restore old scores.
                        arcade_db( 'DELETE FROM ' . arcade_table( 'runs' ) );
                    }
                    arcade_db( 'COMMIT' );
                    $message = arcade_t( $task . '_done' );
                } catch ( Throwable $e ) {
                    arcade_db( 'ROLLBACK' );
                    throw $e;
                }
            } elseif (
                in_array( $task, array( 'delete', 'reset_scores', 'delete_comment', 'delete_category' ), true ) &&
                arcade_request( 'confirmed' ) === '1'
            ) {
                arcade_db( 'START TRANSACTION' );
                try {
                    if ( $task === 'delete_comment' ) {
                        arcade_db( 'DELETE FROM ' . arcade_table( 'content' ) . " WHERE record_type='comment' AND id=" . $id );
                    } elseif ( $task === 'delete_category' ) {
                        $count = arcade_one(
                            'SELECT COUNT(*) AS total FROM ' . arcade_table( 'content' ) .
                                " WHERE record_type='game' AND category_id=" . $id
                        );
                        if ( (int) $count['total'] > 0 || $id === 1 ) {
                            throw new InvalidArgumentException( arcade_t( 'invalid_fields' ) );
                        }
                        arcade_db( 'DELETE FROM ' . arcade_table( 'content' ) . " WHERE record_type='category' AND id=" . $id );
                    } else {
                        arcade_db( 'DELETE FROM ' . arcade_table( 'scores' ) . ' WHERE game_id=' . $id );
                        arcade_db( 'DELETE FROM ' . arcade_table( 'runs' ) . ' WHERE game_id=' . $id );
                        if ( $task === 'delete' ) {
                            foreach ( array( 'favourites' ) as $table ) {
                                arcade_db( 'DELETE FROM ' . arcade_table( $table ) . ' WHERE game_id=' . $id );
                            }
                            arcade_db( 'DELETE FROM ' . arcade_table( 'content' ) . " WHERE record_type='comment' AND game_id=" . $id );
                            arcade_db( 'DELETE FROM ' . arcade_table( 'content' ) . " WHERE record_type='game' AND id=" . $id );
                        }
                    }
                    arcade_db( 'COMMIT' );
                    $message = arcade_t( 'saved' );
                } catch ( Throwable $e ) {
                    arcade_db( 'ROLLBACK' );
                    throw $e;
                }
            }
        }
    }
} catch ( InvalidArgumentException $e ) {
    $message = $e->getMessage();
} catch ( Throwable $e ) {
    error_log( 'Arcade admin: ' . $e->getMessage() );
    $message = arcade_t( 'database_error' );
}

// Remove files created by a request that did not complete successfully.
foreach ( $uploaded_files as $uploaded_path ) {
    @unlink( $uploaded_path );
}

// Failed uploads are rolled back; do not show their names as existing server files.
if ( $game_form && $uploaded_files ) {
    foreach ( array( 'swf', 'picture' ) as $field ) {
        if ( isset( $upload_result['uploads'][ $field ] ) ) {
            $game_form[ $field ] = arcade_request( $field );
        }
    }
}

get_header();

evo_admin_module_header(
    arcade_t( 'admin' ),
    '',
    get_admin_filename() . '.php?op=arcade',
    arcade_t( 'url_label' )
);

opentable();
echo '<section class="evo-arcade">';

if ( $message ) {
    echo '<p role="status" class="arcade-notice">' . arcade_e( $message ) . '</p>';
}

/**
 * Output the opening administrator form tag and hidden request fields.
 *
 * Includes the CSRF field and enables multipart uploads for game forms.
 * The caller must render the remaining controls and close the form.
 *
 * @global string $admin_url Arcade administration endpoint.
 *
 * @param string $task Action handled by the administrator controller.
 * @param int    $id   Existing record ID, or zero when creating a record.
 *
 * @return void
 * @throws RuntimeException If the CSRF helper cannot start a session.
 * @throws Exception If secure CSRF token generation fails.
 */
function arcade_admin_form( $task, $id = 0 ) {
    global $admin_url;

    echo '<form method="post"' . ( $task === 'game' ? ' enctype="multipart/form-data"' : '' ) . ' action="' .
        arcade_e( $admin_url ) . '"><input type="hidden" name="task" value="' . arcade_e( $task ) .
        '"><input type="hidden" name="id" value="' . (int) $id . '">';
    arcade_csrf();
}

/**
 * Output SWF/HTML game and thumbnail upload controls with translated upload guidance.
 *
 * Used by the unified game installation/edit form.
 * The caller must open a form with multipart/form-data encoding first.
 *
 * File types are suggested through the accept attribute; uploaded files must
 * still be validated by the server-side upload handler.
 *
 * @param bool $required Whether both file inputs require a selection.
 *
 * @return void
 */
function arcade_admin_upload_fields( $required = false ) {
    $attribute = $required ? ' required' : '';

    echo '<div class="arcade-upload-fields"><label>' . arcade_e( arcade_t( 'upload_swf' ) ) .
        '<input type="file" name="swf_upload" accept=".swf,.html,.htm"' . $attribute . '></label><label>' .
        arcade_e( arcade_t( 'upload_picture' ) ) .
        '<input type="file" name="picture_upload" accept=".gif,.png,.jpg,.jpeg,.webp"' . $attribute .
        '></label></div><p class="arcade-admin-meta">' . arcade_e( arcade_t( 'upload_help' ) ) . '</p>';
}

// Render installation, moderation, game forms and module settings.
try {
    if ( ! arcade_installed() ) {
        echo '<p>' . arcade_e( arcade_t( 'storage_install_help' ) ) . '</p>';
        arcade_admin_form( 'install' );
        arcade_button( 'storage_install' );
        echo '</form></section>';
        closetable();
        get_footer();
        return;
    }
    $confirm = arcade_request( 'confirm' );
    if ( in_array( $confirm, array( 'delete', 'reset_scores', 'delete_comment', 'delete_category', 'reset_all_plays', 'reset_all_scores' ), true ) ) {
        $global_reset = in_array( $confirm, array( 'reset_all_plays', 'reset_all_scores' ), true );
        echo '<div class="arcade-panel arcade-panel-confirm"><h2>' . arcade_e( arcade_t( $global_reset ? $confirm : 'confirm_delete' ) ) . '</h2>';
        arcade_admin_form( $confirm, (int) arcade_request( 'id' ) );
        echo '<input type="hidden" name="confirmed" value="1">';
        if ( $global_reset ) {
            echo '<p>' . arcade_e( arcade_t( $confirm . '_help' ) ) . '</p><label><span>' .
                '<input type="checkbox" name="reset_acknowledged" value="1" required> ' .
                arcade_e( arcade_t( 'reset_acknowledge' ) ) . '</span></label>';
        }
        arcade_button( $global_reset ? $confirm : 'confirm_delete' );
        echo ' <a href="' . arcade_e( $admin_url ) . '">' . arcade_e( arcade_t( 'cancel' ) ) . '</a></form></div>';
    }
    $cats = arcade_rows( 'SELECT * FROM ' . arcade_table( 'content' ) . " WHERE record_type='category' ORDER BY position,name" );
    $edit =
        $game_form ??
        arcade_one( 'SELECT * FROM ' . arcade_table( 'content' ) . " WHERE record_type='game' AND id=" . (int) arcade_request( 'edit' ) );
    echo '<div class="arcade-admin-grid"><div><h2>' . arcade_e( arcade_t( 'games' ) ) .
        '</h2><p><a href="' . arcade_e( $admin_url . '#arcade-game-form' ) . '">' .
        arcade_e( arcade_t( 'add_game' ) ) . '</a></p><div class="arcade-admin-list">';
    $total        = arcade_one( 'SELECT COUNT(*) AS total FROM ' . arcade_table( 'content' ) . " WHERE record_type='game'" );
    $admin_pages  = max( 1, (int) ceil( (int) $total['total'] / 50 ) );
    $admin_page   = max( 1, min( $admin_pages, (int) arcade_request( 'page', '1' ) ) );
    $admin_offset = ( $admin_page - 1 ) * 50;
    foreach (
        arcade_rows(
            'SELECT g.*,c.name AS category_name FROM ' . arcade_table( 'content' ) . ' g LEFT JOIN ' .
                arcade_table( 'content' ) . " c ON c.id=g.category_id AND c.record_type='category' WHERE g.record_type='game' ORDER BY g.name LIMIT " .
                $admin_offset . ',50'
        )
        as $g
    ) {
        echo '<div class="arcade-admin-row"><div class="arcade-admin-game">';
        if ( $g['picture'] && arcade_file( $g['picture'], true ) ) {
            echo '<img src="modules/Arcade/games/pics/' . arcade_e( $g['picture'] ) .
                '" alt="" loading="lazy">';
        }
        echo '<div><strong>' . arcade_e( arcade_game_title( $g['name'] ) ) . '</strong><div class="arcade-admin-meta">' .
            arcade_e( arcade_t( 'plays' ) ) . ': ' . (int) $g['plays'] . ' · ' .
            arcade_e( arcade_t( $g['active'] ? 'active' : 'disabled' ) ) .
            '</div><div class="arcade-admin-meta">' . arcade_e( arcade_t( 'category' ) ) . ': ' .
            arcade_e( $g['category_name'] ?? arcade_t( 'uncategorised' ) ) . '</div></div></div><nav>';
        foreach (
            array( 'edit' => 'edit', 'reset_scores' => 'reset_scores', 'delete' => 'delete' )
            as $key => $label
        ) {
            $url =
                $admin_url .
                    ( $key === 'edit' ? '&edit=' . (int) $g['id'] . '#arcade-game-form' : '&confirm=' . $key . '&id=' . (int) $g['id'] );
            echo '<a href="' . arcade_e( $url ) . '">' . arcade_e( arcade_t( $label ) ) . '</a>';
        }
        echo '</nav></div>';
    }
    echo '</div>';
    echo get_pagination(
        array(
            'url'                  => $admin_url,
            'total'                => (int) $total['total'],
            'per_page'             => 50,
            'current'              => $admin_page,
            'container'            => 'nav',
            'container_class'      => 'pagination-wrap',
            'container_aria_label' => arcade_t( 'games' ),
        )
    );
    echo '<h2>' . arcade_e( arcade_t( 'categories' ) ) . '</h2>';
    foreach ( $cats as $c ) {
        echo '<div class="arcade-panel">';
        arcade_admin_form( 'category', $c['id'] );
        arcade_input( 'name', arcade_t( 'name' ), $c['name'] );
        arcade_input( 'position', arcade_t( 'order' ), $c['position'], 'number' );
        arcade_input( 'access', arcade_t( 'access' ), $c['access'], 'number' );
        arcade_button( 'save' );
        echo ' <a href="' . arcade_e( $admin_url . '&confirm=delete_category&id=' . (int) $c['id'] ) . '">' .
            arcade_e( arcade_t( 'delete' ) ) . '</a></form></div>';
    }
    arcade_admin_form( 'category' );
    arcade_input( 'name', arcade_t( 'name' ) );
    arcade_button( 'save' );
    echo '</form>';
    echo '<h2>' . arcade_e( arcade_t( 'comments' ) ) . '</h2><div class="arcade-admin-comments">';
    foreach (
        arcade_rows( 'SELECT * FROM ' . arcade_table( 'content' ) . " WHERE record_type='comment' ORDER BY created DESC LIMIT 50" )
        as $c
    ) {
        echo '<div class="arcade-comment"><p>' . arcade_e( $c['message'] ) . '</p><a href="' .
            arcade_e( $admin_url . '&confirm=delete_comment&id=' . (int) $c['id'] ) . '">' .
            arcade_e( arcade_t( 'delete' ) ) . '</a></div>';
    }
    echo '</div></div><aside class="arcade-admin-sidebar">';
    $editing = ! empty( $edit['id'] );
    echo '<section id="arcade-game-form" class="arcade-panel"><h2>' .
        arcade_e( arcade_t( $editing ? 'edit_game' : 'add_game' ) ) . '</h2><p class="arcade-admin-meta">' .
        arcade_e( arcade_t( 'game_form_help' ) ) . '</p>';
    arcade_admin_form( 'game', $edit['id'] ?? 0 );
    arcade_admin_upload_fields();
    echo '<details' . ( ! empty( $edit['swf'] ) || ! empty( $edit['picture'] ) ? ' open' : '' ) .
        '><summary>' . arcade_e( arcade_t( 'existing_files' ) ) . '</summary><p>' .
        arcade_e( arcade_t( 'existing_help' ) ) . '</p>';
    foreach ( array( 'swf', 'picture' ) as $key ) {
        arcade_input( $key, arcade_t( $key ), $edit[$key] ?? '' );
    }
    echo '</details>';
    arcade_input( 'name', arcade_t( $editing ? 'name' : 'custom_title' ), arcade_game_title( $edit['name'] ?? '' ) );
    echo '<label>' . arcade_e( arcade_t( 'description' ) ) .
        '<textarea name="description" maxlength="4000">' . arcade_e( $edit['description'] ?? '' ) .
        '</textarea></label>';
    echo '<label>' . arcade_e( arcade_t( 'category' ) ) . '<select name="category_id">';
    foreach ( $cats as $c ) {
        echo '<option value="' . (int) $c['id'] . '"' .
            ( (int) ( $edit['category_id'] ?? 1 ) === (int) $c['id'] ? ' selected' : '' ) . '>' .
            arcade_e( $c['name'] ) . '</option>';
    }
    echo '</select></label><details' . ( $manual_open ? ' open' : '' ) . '><summary>' .
        arcade_e( arcade_t( 'advanced_settings' ) ) . '</summary><fieldset><legend>' . arcade_e( arcade_t( 'scoring_settings' ) ) .
        '</legend><label>' . arcade_e( arcade_t( 'protocol_label' ) ) . '<select name="type">';
    foreach ( array( 0, 3, 4, 5, 6, 7, 8 ) as $protocol ) {
        echo '<option value="' . $protocol . '"' .
            ( (int) ( $edit['type'] ?? 0 ) === $protocol ? ' selected' : '' ) . '>' .
            arcade_e( arcade_t( 'protocol_' . $protocol ) ) . '</option>';
    }
    echo '</select></label>';
    arcade_input( 'scorevar', arcade_t( 'scorevar' ), $edit['scorevar'] ?? 'vscore' );
    echo '<p>' . arcade_e( arcade_t( 'detect_help' ) ) . '</p></fieldset>';
    arcade_input( 'position', arcade_t( 'order' ), $edit['position'] ?? 0, 'number' );
    arcade_input( 'width', arcade_t( 'width_optional' ), $game_form ? arcade_request( 'width' ) : ( $edit['width'] ?? '' ), 'number' );
    arcade_input( 'height', arcade_t( 'height_optional' ), $game_form ? arcade_request( 'height' ) : ( $edit['height'] ?? '' ), 'number' );
    echo '<p class="arcade-admin-meta">' . arcade_e( arcade_t( 'dimensions_help' ) ) .
        '</p></details><label><span><input type="checkbox" name="active" value="1"' .
        ( (int) ( $edit['active'] ?? 1 ) === 1 ? ' checked' : '' ) . '> ' . arcade_e( arcade_t( 'active' ) ) .
        '</span></label>';
    arcade_button( 'save' );
    if ( $editing ) {
        echo ' <a href="' . arcade_e( $admin_url ) . '">' . arcade_e( arcade_t( 'cancel' ) ) . '</a>';
    }
    echo '</form></section>';
    $settings = arcade_settings();
    echo '<div class="arcade-panel"><h2>' . arcade_e( arcade_t( 'settings' ) ) . '</h2>';
    arcade_admin_form( 'settings' );
    foreach ( array( 'title', 'announcement', 'per_page' ) as $key ) {
        arcade_input( $key, arcade_t( $key ), $settings[$key], $key === 'per_page' ? 'number' : 'text' );
    }
    arcade_button( 'save' );
    echo '</form><p>' . arcade_e( arcade_t( 'ruffle_setup' ) ) . '</p></div>';
    echo '<div class="arcade-panel"><h2>' . arcade_e( arcade_t( 'maintenance' ) ) . '</h2>';
    foreach ( array( 'reset_all_plays', 'reset_all_scores' ) as $reset_task ) {
        echo '<p>' . arcade_e( arcade_t( $reset_task . '_help' ) ) . '</p><p><a href="' .
            arcade_e( $admin_url . '&confirm=' . $reset_task ) . '">' .
            arcade_e( arcade_t( $reset_task ) ) . '</a></p>';
    }
    echo '</div></aside></div>';
} catch ( Throwable $e ) {
    error_log( 'Arcade admin rendering: ' . $e->getMessage() );
    echo '<p>' . arcade_e( arcade_t( 'database_error' ) ) . '</p>';
}
echo '</section>';
closetable();
get_footer();
