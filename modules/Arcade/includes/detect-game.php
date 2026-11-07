<?php
/**
 * Identify supported legacy score protocols without executing a SWF.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

/**
 * Detect a supported score protocol from an uncompressed or zlib-compressed SWF.
 *
 * Inspects files up to 32 MiB and limits the declared SWF body to 16 MiB.
 * Checks known markers for session handshake, IBPro, GFARV2 and pnFlashGames.
 * GFARV2 detection checks the supplied score hint before falling back to vscore.
 *
 * This is a best-effort signature check, not an ActionScript interpreter.
 * LZMA-compressed, ambiguous and unrecognised games require manual settings.
 * A result is returned only when exactly one supported signature matches.
 *
 * @param string $path       Local SWF file path.
 * @param string $score_hint Existing score variable to check for GFARV2 games.
 *
 * @return array{type: int, scorevar: string}|array{} Detected settings, or an
 *                                                      empty array on failure.
 */
function arcade_detect_game( $path, $score_hint = 'vscore' ) {
    if ( ! is_file( $path ) || ! is_readable( $path ) ) {
        return array();
    }

    $size = filesize( $path );
    if ( $size === false || $size < 8 || $size > 32 * 1024 * 1024 ) {
        return array();
    }

    $bytes = file_get_contents( $path );
    if ( ! is_string( $bytes ) || strlen( $bytes ) < 8 ) {
        return array();
    }

    $signature = substr( $bytes, 0, 3 );

    $length    = unpack( 'Vlength', substr( $bytes, 4, 4 ) );
    $limit     = 16 * 1024 * 1024;
    if ( $length['length'] < 8 || $length['length'] > $limit + 8 ) {
        return array();
    }

    if ( $signature === 'CWS' && function_exists( 'gzuncompress' ) ) {
        $body = @gzuncompress( substr( $bytes, 8 ), $limit );
    } elseif ( $signature === 'FWS' ) {
        $body = substr( $bytes, 8 );
    } else {
        return array();
    }

    if ( ! is_string( $body ) || strlen( $body ) !== $length['length'] - 8 ) {
        return array();
    }

    $matches = array();

    if (
        strpos( $body, "pnFlashGames\0" ) !== false
        && strpos( $body, "storeScore\0" ) !== false
        && strpos( $body, "storeScore_Result\0" ) !== false
        && strpos( $body, "sendAndLoad\0" ) !== false
        && strpos( $body, "score\0" ) !== false
    ) {
        $matches[] = array(
            'type'     => 8,
            'scorevar' => 'score',
        );
    }

    if (
        strpos( $body, 'sessionstart' ) !== false
        && strpos( $body, 'permrequest' ) !== false
        && strpos( $body, 'microone' ) !== false
        && strpos( $body, 'arcade.php' ) !== false
    ) {
        $matches[] = array(
            'type'     => 6,
            'scorevar' => 'score',
        );
    }

    if (
        strpos( $body, 'act=Arcade' ) !== false
        && strpos( $body, 'do=newscore' ) !== false
        && strpos( $body, "gscore\0" ) !== false
        && strpos( $body, "gname\0" ) !== false
    ) {
        $matches[] = array(
            'type'     => 7,
            'scorevar' => 'gscore',
        );
    }

    if (
        strpos( $body, 'GFARV2' ) !== false
        && strpos( $body, 'newhash' ) !== false
        && strpos( $body, 'hashoffset' ) !== false
    ) {
        $field = '';

        foreach ( array_unique( array( $score_hint, 'vscore' ) ) as $candidate ) {
            if (
                preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/D', $candidate )
                && strpos( $body, $candidate . "\0" ) !== false
            ) {
                $field = $candidate;
                break;
            }
        }

        if ( $field !== '' ) {
            $matches[] = array(
                'type'     => 3,
                'scorevar' => $field,
            );
        }
    }

    return count( $matches ) === 1 ? $matches[0] : array();
}

/**
 * Read the SWF stage dimensions without executing ActionScript.
 *
 * Supports uncompressed FWS and zlib-compressed CWS files up to 32 MiB.
 * CWS decompression is limited to 16 MiB and requires gzuncompress().
 * Reads the signed RECT coordinates and converts twips to rounded pixels.
 *
 * @param string $path Local SWF file path.
 *
 * @return array{0: int, 1: int}|array{} Width and height in pixels, or an empty
 *                                     array for unsupported or invalid content.
 *                                     Dimensions must be between 1 and 16384.
 */
function arcade_swf_dimensions( $path ) {
    $size = is_file( $path ) ? filesize( $path ) : false;
    if ( $size === false || $size < 9 || $size > 32 * 1024 * 1024 ) {
        return array();
    }

    $bytes = @file_get_contents( $path );
    if ( ! is_string( $bytes ) || strlen( $bytes ) < 9 ) {
        return array();
    }

    $signature = substr( $bytes, 0, 3 );
    if ( $signature === 'CWS' && function_exists( 'gzuncompress' ) ) {
        $body = @gzuncompress( substr( $bytes, 8 ), 16 * 1024 * 1024 );
    } elseif ( $signature === 'FWS' ) {
        $body = substr( $bytes, 8, 17 );
    } else {
        return array();
    }

    if ( ! is_string( $body ) || $body === '' ) {
        return array();
    }

    $bits = '';
    foreach ( str_split( substr( $body, 0, 17 ) ) as $byte ) {
        $bits .= str_pad( decbin( ord( $byte ) ), 8, '0', STR_PAD_LEFT );
    }

    $length = bindec( substr( $bits, 0, 5 ) );
    if ( $length < 1 || strlen( $bits ) < 5 + 4 * $length ) {
        return array();
    }

    $coordinates = array();
    for ( $i = 0; $i < 4; $i++ ) {
        $value = substr( $bits, 5 + $i * $length, $length );
        $coordinates[] = bindec( $value ) - ( $value[0] === '1' ? pow( 2, $length ) : 0 );
    }

    $width  = (int) round( ( $coordinates[1] - $coordinates[0] ) / 20 );
    $height = (int) round( ( $coordinates[3] - $coordinates[2] ) / 20 );
    return $width > 0 && $height > 0 && $width <= 16384 && $height <= 16384
        ? array( $width, $height )
        : array();
}

/**
 * Check an HTML game upload without running its scripts.
 *
 * Accept a complete HTML document up to 32 MiB. Reject binary data and PHP
 * opening tags so game uploads remain browser documents rather than PHP files.
 * Games are administrator-provided executable browser code, as with SWFs.
 *
 * @param string $path Temporary uploaded file path.
 * @return bool Whether the file is a supported HTML document.
 */
function arcade_html_document( $path ) {
    $size = is_file( $path ) ? filesize( $path ) : false;
    if ( $size === false || $size < 1 || $size > 32 * 1024 * 1024 ) {
        return false;
    }
    $html = file_get_contents( $path );
    return is_string( $html )
        && strpos( $html, "\0" ) === false
        && stripos( $html, '<?' ) === false
        && preg_match( '/<(?:!doctype\s+html\b|html\b)/i', $html ) === 1;
}
