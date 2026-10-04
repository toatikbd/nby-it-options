<?php
/**
 * Secure resume upload and storage.
 *
 * Files are validated by extension, size, real MIME type (finfo) and file
 * signature, then stored with a random, unguessable name in a private directory
 * outside the web root (or, if not writable, under uploads protected by
 * .htaccess / web.config / index.php — nginx then needs a deny rule, see README).
 * Files are only ever served through the capability-checked download action in
 * NBY_IT_Options_Application.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_File_Upload {

	const DIR_NAME = 'nby-it-options-private/resumes';

	const LOCATION_OPTION = 'nby_it_options_resume_location';

	/**
	 * Supported resume types: extension => accepted real MIME types.
	 *
	 * @return array<string, array>
	 */
	public static function supported_types() {
		return array(
			'pdf'  => array( 'application/pdf', 'application/x-pdf' ),
			'doc'  => array( 'application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/cdfv2', 'application/vnd.ms-word' ),
			'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed' ),
		);
	}

	/**
	 * Canonical MIME type stored and sent on download.
	 *
	 * @param string $ext Extension.
	 * @return string
	 */
	public static function canonical_mime( $ext ) {
		$map = array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		return isset( $map[ $ext ] ) ? $map[ $ext ] : 'application/octet-stream';
	}

	/**
	 * Absolute path of the private resume directory (created and protected on demand).
	 *
	 * Preferred location is outside the web root (next to the WordPress folder),
	 * so files can never be requested by URL regardless of web server. If that is
	 * not writable, the uploads folder is used with deny-all rules. The choice is
	 * stored once (as "outside"/"uploads", not an absolute path, so it survives
	 * site migrations) and can be overridden with the `nby_it_options_resume_dir` filter.
	 *
	 * @return string|WP_Error
	 */
	public static function directory() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$location = get_option( self::LOCATION_OPTION );
		if ( ! in_array( $location, array( 'outside', 'uploads' ), true ) ) {
			$location = self::can_store_outside_webroot() ? 'outside' : 'uploads';
			update_option( self::LOCATION_OPTION, $location, false );
		}

		if ( 'outside' === $location ) {
			$dir = dirname( untrailingslashit( wp_normalize_path( ABSPATH ) ) ) . '/' . self::DIR_NAME;
		} else {
			$uploads = wp_upload_dir( null, false );
			if ( ! empty( $uploads['error'] ) ) {
				return new WP_Error( 'nby_upload_dir', $uploads['error'] );
			}
			$dir = trailingslashit( $uploads['basedir'] ) . self::DIR_NAME;
		}

		$dir = apply_filters( 'nby_it_options_resume_dir', $dir, $location );
		$dir = untrailingslashit( wp_normalize_path( $dir ) );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'nby_upload_dir', 'Unable to create resume directory.' );
		}

		// Deny rules also matter outside the web root in case the docroot is the parent folder.
		self::protect_directory( $dir );
		self::protect_directory( dirname( $dir ) );

		$cache = $dir;
		return $dir;
	}

	/**
	 * Where resumes are stored: "outside" (outside the web root) or "uploads".
	 *
	 * @return string
	 */
	public static function location() {
		$dir = self::directory();
		return is_wp_error( $dir ) ? '' : (string) get_option( self::LOCATION_OPTION );
	}

	/**
	 * Whether the folder above the WordPress root can hold private files.
	 *
	 * @return bool
	 */
	private static function can_store_outside_webroot() {
		if ( ! apply_filters( 'nby_it_options_store_outside_webroot', true ) ) {
			return false;
		}
		$parent = dirname( untrailingslashit( wp_normalize_path( ABSPATH ) ) );
		// Suppress open_basedir warnings; a restricted path simply is not usable.
		return @is_dir( $parent ) && wp_is_writable( $parent ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Drop deny-all files into a directory.
	 *
	 * @param string $dir Directory.
	 */
	public static function protect_directory( $dir ) {
		$files = array(
			'.htaccess'  => "# Deny direct access to private files.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\nOptions -Indexes\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
		);
		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $dir ) . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}

	/**
	 * Validate an entry of $_FILES without moving it.
	 *
	 * @param array $file          Entry from $_FILES.
	 * @param array $allowed_exts  Allowed extensions (subset of supported_types()).
	 * @param int   $max_bytes     Maximum size in bytes.
	 * @return string|WP_Error Validated lowercase extension.
	 */
	public static function validate( $file, array $allowed_exts, $max_bytes ) {
		$types_label = strtoupper( implode( ', ', $allowed_exts ) );

		if ( empty( $file ) || ! is_array( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return new WP_Error( 'resume', __( 'Please upload your resume / CV.', 'nby-it-options' ) );
		}

		$error = (int) $file['error'];
		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			/* translators: %s: maximum size, e.g. 5 MB. */
			return new WP_Error( 'resume', sprintf( __( 'Your resume is too large. The maximum size is %s.', 'nby-it-options' ), size_format( $max_bytes ) ) );
		}
		if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'resume', __( 'Your resume could not be uploaded. Please try again.', 'nby-it-options' ) );
		}

		$size = (int) filesize( $file['tmp_name'] );
		if ( $size <= 0 ) {
			return new WP_Error( 'resume', __( 'The uploaded resume is empty.', 'nby-it-options' ) );
		}
		if ( $size > $max_bytes ) {
			/* translators: %s: maximum size, e.g. 5 MB. */
			return new WP_Error( 'resume', sprintf( __( 'Your resume is too large. The maximum size is %s.', 'nby-it-options' ), size_format( $max_bytes ) ) );
		}

		// Extension of the final segment only, so "cv.php.pdf" -> pdf and "cv.pdf.php" -> php.
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_basename( (string) $file['name'] ) ) : '';
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$supported = self::supported_types();

		if ( '' === $ext || ! in_array( $ext, $allowed_exts, true ) || ! isset( $supported[ $ext ] ) ) {
			/* translators: %s: allowed file types. */
			return new WP_Error( 'resume', sprintf( __( 'Invalid file type. Please upload one of: %s.', 'nby-it-options' ), $types_label ) );
		}

		// Never trust the browser-supplied MIME: detect it from content.
		if ( ! self::content_matches( $file['tmp_name'], $ext ) ) {
			/* translators: %s: allowed file types. */
			return new WP_Error( 'resume', sprintf( __( 'The file content does not match a valid %s document.', 'nby-it-options' ), strtoupper( $ext ) ) );
		}

		return $ext;
	}

	/**
	 * Check real MIME type and file signature against the extension.
	 *
	 * @param string $path File path.
	 * @param string $ext  Claimed extension.
	 * @return bool
	 */
	private static function content_matches( $path, $ext ) {
		$supported = self::supported_types();

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = $finfo ? strtolower( (string) finfo_file( $finfo, $path ) ) : '';
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			if ( '' !== $mime && ! in_array( $mime, $supported[ $ext ], true ) ) {
				return false;
			}
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return false;
		}
		$head = (string) fread( $handle, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		switch ( $ext ) {
			case 'pdf':
				return 0 === strpos( $head, '%PDF-' );

			case 'doc':
				return "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" === $head;

			case 'docx':
				if ( 0 !== strpos( $head, "PK\x03\x04" ) ) {
					return false;
				}
				if ( class_exists( 'ZipArchive' ) ) {
					$zip = new ZipArchive();
					if ( true !== $zip->open( $path ) ) {
						return false;
					}
					$is_word = false !== $zip->locateName( 'word/document.xml' ) && false !== $zip->locateName( '[Content_Types].xml' );
					$zip->close();
					return $is_word;
				}
				return true;
		}

		return false;
	}

	/**
	 * Move a validated upload into private storage.
	 *
	 * @param array  $file      Entry from $_FILES.
	 * @param string $ext       Validated extension.
	 * @param string $applicant Applicant name, used in the filename.
	 * @return array|WP_Error { file: relative path, name: original name, mime: string, size: int }
	 */
	public static function store( $file, $ext, $applicant ) {
		$dir = self::directory();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$slug = sanitize_title( $applicant );
		$slug = $slug ? substr( $slug, 0, 40 ) : 'applicant';
		// e.g. john-doe-resume-20261004-k3j9x2m1q8zw4b7p.pdf (random part makes the URL unguessable).
		$filename = sprintf( '%s-resume-%s-%s.%s', $slug, gmdate( 'Ymd' ), strtolower( wp_generate_password( 16, false ) ), $ext );

		$mimes = array();
		foreach ( self::supported_types() as $type_ext => $unused ) {
			$mimes[ $type_ext ] = self::canonical_mime( $type_ext );
		}

		$override_dir = static function ( $uploads ) use ( $dir ) {
			$uploads['path']   = $dir;
			$uploads['url']    = '';
			$uploads['subdir'] = '';
			return $uploads;
		};

		$file['name'] = $filename;

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		add_filter( 'upload_dir', $override_dir, 999 );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'test_type'                => false, // Already validated by content above.
				'mimes'                    => $mimes,
				'unique_filename_callback' => static function () use ( $filename ) {
					return $filename;
				},
			)
		);
		remove_filter( 'upload_dir', $override_dir, 999 );

		if ( empty( $result['file'] ) || ! empty( $result['error'] ) ) {
			return new WP_Error( 'resume', __( 'Your resume could not be saved. Please try again.', 'nby-it-options' ) );
		}

		// Owner read/write only where the filesystem supports it.
		@chmod( $result['file'], 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		return array(
			'file' => wp_basename( $result['file'] ),
			'mime' => self::canonical_mime( $ext ),
			'size' => (int) filesize( $result['file'] ),
		);
	}

	/**
	 * Absolute path for a stored resume filename, or false if invalid/missing.
	 *
	 * @param string $filename Stored filename (basename only).
	 * @return string|false
	 */
	public static function path( $filename ) {
		$filename = wp_basename( (string) $filename );
		if ( '' === $filename || ! preg_match( '/^[a-z0-9-]+\.(pdf|docx?)$/', $filename ) ) {
			return false;
		}
		$dir = self::directory();
		if ( is_wp_error( $dir ) ) {
			return false;
		}
		$path = $dir . '/' . $filename;
		return is_file( $path ) ? $path : false;
	}

	/**
	 * Delete a stored resume.
	 *
	 * @param string $filename Stored filename.
	 */
	public static function delete( $filename ) {
		$path = self::path( $filename );
		if ( $path ) {
			wp_delete_file( $path );
		}
	}
}
