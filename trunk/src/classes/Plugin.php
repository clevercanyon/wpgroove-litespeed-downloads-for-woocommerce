<?php
/** WP Groove™ <https://wpgroove.com>
 *  _       _  ___       ___
 * ( )  _  ( )(  _`\    (  _`\
 * | | ( ) | || |_) )   | ( (_) _ __   _      _    _   _    __  ™
 * | | | | | || ,__/'   | |___ ( '__)/'_`\  /'_`\ ( ) ( ) /'__`\
 * | (_/ \_) || |       | (_, )| |  ( (_) )( (_) )| \_/ |(  ___/
 * `\___x___/'(_)       (____/'(_)  `\___/'`\___/'`\___/'`\____)
 */
namespace WP_Groove\LiteSpeed_Downloads_For_WooCommerce;

/**
 * Dependencies.
 *
 * @since 1.0.0
 */
use Clever_Canyon\Utilities\OOPs\Version_1_0_0 as U;
use WP_Groove\Framework\Utilities\OOPs\Version_1_0_0 as UU;
use WP_Groove\Framework\Plugin\Version_1_0_0\{ Base };

/**
 * Plugin.
 *
 * @since 1.0.0
 */
class Plugin extends Base {
	/**
	 * On `init` hook.
	 *
	 * @since 1.0.0
	 */
	public function on_init() : void {
		// Right before WooCommerce fires: <https://git.io/JMmrj>
		add_action( 'woocommerce_download_file_xsendfile', [ $this, 'on_woocommerce_download_file_xsendfile' ], 9, 2 );
	}

	/**
	 * On `woocommerce_download_file_xsendfile` hook.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path.
	 * @param string $file_name File name.
	 */
	public function on_woocommerce_download_file_xsendfile( string $file_path, string $file_name ) : void {
		if ( stripos( getenv( 'SERVER_SOFTWARE' ), 'litespeed' ) === false ) {
			return; // Not LiteSpeed web server. Pass back to WooCommerce core.
		}

		if ( ! class_exists( 'WC_Download_Handler' ) ) {
			wc_get_logger()->warning(
				sprintf(
					/* translators: %1$s contains the filepath of the digital asset. */
					__( '%1$s could not be served using the X-LiteSpeed-Location method. `WC_Download_Handler` class is missing. Passing back to WooCommerce core.' ),
					$file_path
				)
			);
			return; // Not possible. Pass back to WooCommerce core.
		}

		if ( ! is_callable( [ \WC_Download_Handler::class, 'parse_file_path' ] ) ) {
			wc_get_logger()->warning(
				sprintf(
					/* translators: %1$s contains the filepath of the digital asset. */
					__( '%1$s could not be served using the X-LiteSpeed-Location method. `WC_Download_Handler::parse_file_path()` is not callable. Passing back to WooCommerce core.' ),
					$file_path
				)
			);
			return; // Not possible. Pass back to WooCommerce core.
		}

		$parsed_file_path = \WC_Download_Handler::parse_file_path( $file_path );

		if ( $parsed_file_path['remote_file'] || ! is_file( $parsed_file_path['file_path'] ) ) {
			return; // Not possible. Pass back to WooCommerce core.
		}
		$file_path = U\Fs::normalize( realpath( $parsed_file_path['file_path'] ) );

		wc_get_logger()->debug(
			sprintf(
				/* translators: %1$s contains the filepath of the digital asset. */
				__( '%1$s is being served using the X-LiteSpeed-Location methodology.' ),
				$file_path
			)
		);
		$this->do_litespeed_download( $file_path, $file_name, $parsed_file_path );
	}

	/**
	 * Does a LiteSpeed download.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path        File path.
	 * @param string $file_name        File name.
	 * @param array  $parsed_file_path Parsed file path.
	 */
	protected function do_litespeed_download( string $file_path, string $file_name, array $parsed_file_path ) : void {
		$this->prep_file_download( $file_path, $file_name );
		$cwd_path = U\Fs::normalize( realpath( getcwd() ) );

		$x_litespeed_path = trim( preg_replace( '/^' . preg_quote( rtrim( $cwd_path, '/' ) . '/', '/' ) . '/ui', '', $file_path ), '/' );
		$x_litespeed_path = apply_filters( $this->var . '_download_file_x_litespeed_file_path', $x_litespeed_path, $file_path, $file_name, $parsed_file_path );
		$x_litespeed_path = '/' . trim( $x_litespeed_path, '/' );

		header( 'X-LiteSpeed-Location: ' . $xsendfile_path );
		exit; // Stop here.
	}

	/**
	 * Preps a file download.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path.
	 * @param string $file_name File name.
	 */
	protected function prep_file_download( string $file_path, string $file_name ) : void {
		wc_set_time_limit( 0 );

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 ); // phpcs:ignore
		}
		@ini_set( 'zlib.output_compression', 'off' ); // phpcs:ignore

		@session_write_close(); // phpcs:ignore -- ending session ok.
		while( @ob_end_clean() ) ; // phpcs:ignore -- cleaning buffers ok.

		wc_nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $this->get_file_mime_type( $file_path ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $file_name ) . '";' );

		if ( $file_size = filesize( $file_path ) ) {
			header( 'Content-Length: ' . $file_size );
		}
	}

	/**
	 * Gets file MIME type.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $file_path File path.
	 *
	 * @return string            Content type.
	 */
	protected function get_file_mime_type( string $file_path ) : string {
		$mime_type      = 'application/force-download';
		$file_extension = strtolower( substr( strrchr( $file_path, '.' ), 1 ) );

		foreach ( get_allowed_mime_types() as $_mime => $_type ) {
			$_mimes = explode( '|', $_mime );

			if ( in_array( $file_extension, $_mimes, true ) ) {
				$mime_type = $_type;
				break;
			}
		}
		return $mime_type;
	}
}
