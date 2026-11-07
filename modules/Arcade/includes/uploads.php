<?php
/**
 * Validate and store Arcade administrator uploads.
 *
 * @package Arcade
 */

defined( 'ADMIN_FILE' ) || exit;

/**
 * Validate and store the optional SWF/HTML game and thumbnail administrator uploads.
 *
 * Validates all supplied uploads before creating destination files. Game files
 * are limited to 32 MiB and validated as a SWF header or an HTML document.
 * Thumbnails are limited to 5 MiB, must match their filename extension, and
 * cannot exceed 8192 pixels in either dimension.
 *
 * Original filenames are preserved for legacy score callbacks. Destination
 * files are created exclusively to avoid overwriting existing game assets.
 * If storage fails, files created by this call are removed where possible.
 *
 * The caller is responsible for administrator authorisation and CSRF checks,
 * and for removing returned files if a later game-save operation fails.
 *
 * @return array{
 *     uploads: array<string, array{name: string, temporary: string, directory: string}>,
 *     created: list<string>
 * } Validated upload metadata and paths of newly created files.
 * @throws InvalidArgumentException If upload validation or file storage fails.
 * @throws Throwable If an unexpected error occurs while storing uploads.
 */
function arcade_admin_uploads() {
    $uploads = array();
    $created = array();

    $fields = array(
        'swf_upload'     => false,
        'picture_upload' => true,
    );

    foreach ( $fields as $field => $picture ) {
        if ( ! isset( $_FILES[ $field ] ) ) {
            continue;
        }

        $file = $_FILES[ $field ];

        if ( ! is_array( $file ) || ! isset( $file['error'] ) || ! is_int( $file['error'] ) ) {
            throw new InvalidArgumentException( arcade_t( 'upload_invalid' ) );
        }

        if ( $file['error'] === UPLOAD_ERR_NO_FILE ) {
            continue;
        }

        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            throw new InvalidArgumentException( arcade_t( 'upload_failed' ) );
        }

        if (
            ! isset( $file['name'], $file['tmp_name'] )
            || ! is_string( $file['name'] )
            || ! is_string( $file['tmp_name'] )
            || ! is_uploaded_file( $file['tmp_name'] )
        ) {
            throw new InvalidArgumentException( arcade_t( 'upload_invalid' ) );
        }

        // Keep game filenames intact: some games submit their original filename as gname.
        $name = $file['name'];

        if ( strlen( $name ) > 150 || ! arcade_file( $name, $picture ) ) {
            throw new InvalidArgumentException( arcade_t( 'upload_filename' ) );
        }

        $size  = filesize( $file['tmp_name'] );
        $limit = $picture ? 5 * 1024 * 1024 : 32 * 1024 * 1024;

        if ( $size === false || $size < 1 || $size > $limit ) {
            throw new InvalidArgumentException( arcade_t( 'upload_size' ) );
        }

        if ( $picture ) {
            $image     = @getimagesize( $file['tmp_name'] );
            $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            $types     = array(
                'gif'  => IMAGETYPE_GIF,
                'png'  => IMAGETYPE_PNG,
                'jpg'  => IMAGETYPE_JPEG,
                'jpeg' => IMAGETYPE_JPEG,
                'webp' => IMAGETYPE_WEBP,
            );

            if (
                ! $image
                || ! isset( $types[ $extension ] )
                || $image[2] !== $types[ $extension ]
                || $image[0] > 8192
                || $image[1] > 8192
            ) {
                throw new InvalidArgumentException( arcade_t( 'upload_image_invalid' ) );
            }
        } elseif ( preg_match( '/\.html?$/iD', $name ) ) {
            if ( ! arcade_html_document( $file['tmp_name'] ) ) {
                throw new InvalidArgumentException( arcade_t( 'upload_html_invalid' ) );
            }
        } else {
            $header = file_get_contents( $file['tmp_name'], false, null, 0, 8 );

            if (
                ! is_string( $header )
                || strlen( $header ) !== 8
                || ! in_array( substr( $header, 0, 3 ), array( 'FWS', 'CWS', 'ZWS' ), true )
                || ord( $header[3] ) === 0
            ) {
                throw new InvalidArgumentException( arcade_t( 'upload_swf_invalid' ) );
            }
        }

        $directory = __DIR__ . '/../games' . ( $picture ? '/pics' : '' );

        $uploads[ $picture ? 'picture' : 'swf' ] = array(
            'name'      => $name,
            'temporary' => $file['tmp_name'],
            'directory' => $directory,
        );
    }

    try {
        foreach ( $uploads as $key => $upload ) {
            $directory = $upload['directory'];

            if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
                throw new InvalidArgumentException( arcade_t( 'upload_directory' ) );
            }

            $path = $directory . '/' . $upload['name'];

            // Exclusive creation prevents accidental replacement of another game's assets.
            $destination = @fopen( $path, 'xb' );

            if ( ! $destination ) {
                throw new InvalidArgumentException( arcade_t( 'upload_exists' ) );
            }

            $created[] = $path;
            $source    = @fopen( $upload['temporary'], 'rb' );
            $copied    = $source ? stream_copy_to_stream( $source, $destination ) : false;

            if ( $source ) {
                fclose( $source );
            }

            fclose( $destination );

            if ( $copied === false || $copied !== filesize( $upload['temporary'] ) ) {
                throw new InvalidArgumentException( arcade_t( 'upload_failed' ) );
            }

            @chmod( $path, 0644 );
        }
    } catch ( Throwable $e ) {
        foreach ( $created as $path ) {
            @unlink( $path );
        }

        throw $e;
    }

    return array(
        'uploads' => $uploads,
        'created' => $created,
    );
}
