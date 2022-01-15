<?php
/**
 * WP Groove™ {@see https://wpgroove.com}
 *  _       _  ___       ___
 * ( )  _  ( )(  _`\    (  _`\
 * | | ( ) | || |_) )   | ( (_) _ __   _      _    _   _    __  ™
 * | | | | | || ,__/'   | |___ ( '__)/'_`\  /'_`\ ( ) ( ) /'__`\
 * | (_/ \_) || |       | (_, )| |  ( (_) )( (_) )| \_/ |(  ___/
 * `\___x___/'(_)       (____/'(_)  `\___/'`\___/'`\___/'`\____)
 */
// <editor-fold desc="Strict types, namespace, use statements, and other headers.">

/**
 * Declarations & namespace.
 *
 * @since 2021-12-25
 */
declare( strict_types = 1 );
namespace WP_Groove\LiteSpeed_Downloads_For_WooCommerce;

/**
 * Utilities.
 *
 * @since 2021-12-15
 */
use Clever_Canyon\{Utilities as U};

/**
 * Framework.
 *
 * @since 2021-12-15
 */
use WP_Groove\{Framework as WPG};

/**
 * Plugin.
 *
 * @since 2021-12-15
 */
use WP_Groove\{LiteSpeed_Downloads_For_WooCommerce as WP};

// </editor-fold>

/**
 * Plugin.
 *
 * @since 2021-12-15
 */
class Plugin extends WPG\A6t\Plugin {
	/**
	 * On `init` hook.
	 *
	 * @since 2021-12-15
	 */
	public function on_init() : void {
		parent::on_init();

		// Right before WooCommerce fires: <https://git.io/JMmrj>.
		add_action( 'woocommerce_download_file_xsendfile', [ $this, 'on_woocommerce_download_file_xsendfile' ], 9, 2 );
	}

	/**
	 * On `woocommerce_download_file_xsendfile` hook.
	 *
	 * @since 2021-12-15
	 *
	 * @param string $file_path File path.
	 * @param string $file_name File name.
	 */
	public function on_woocommerce_download_file_xsendfile( string $file_path, string $file_name ) : void {
		if ( false === mb_stripos( U\Env::var( 'SERVER_SOFTWARE' ), 'litespeed' ) ) {
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

		if ( $parsed_file_path[ 'remote_file' ] || ! is_file( $parsed_file_path[ 'file_path' ] ) ) {
			return; // Not possible. Pass back to WooCommerce core.
		}
		$file_path = U\Fs::normalize( realpath( $parsed_file_path[ 'file_path' ] ) );

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
	 * @since 2021-12-15
	 *
	 * @param string $file_path        File path.
	 * @param string $file_name        File name.
	 * @param array  $parsed_file_path Parsed file path.
	 */
	protected function do_litespeed_download( string $file_path, string $file_name, array $parsed_file_path ) /* : never */ : void {
		$this->prep_file_download( $file_path, $file_name );

		$file_path             = U\Fs::normalize( realpath( $file_path ) );
		$x_litespeed_base_path = U\Fs::normalize( realpath( U\Env::var( 'DOCUMENT_ROOT' ) ) );
		$x_litespeed_location  = '/' . U\Dir::subpath( $x_litespeed_base_path, $file_path );
		$x_litespeed_location  = apply_filters(
			$this->var_prefix . 'download_file_x_litespeed_location',
			$x_litespeed_location,
			$file_path,
			$file_name,
			$parsed_file_path,
		);
		header( 'x-litespeed-location: ' . $x_litespeed_location );
		exit; // Stop here.
	}

	/**
	 * Preps a file download.
	 *
	 * @since 2021-12-15
	 *
	 * @param string $file_path File path.
	 * @param string $file_name File name.
	 */
	protected function prep_file_download( string $file_path, string $file_name ) : void {
		U\Env::prep_for_file_download();

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . WPG\File::mime_type( $file_path, 'application/force-download' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $file_name ) . '";' );

		if ( $file_size = filesize( $file_path ) ) {
			header( 'Content-Length: ' . $file_size );
		}
	}
}
