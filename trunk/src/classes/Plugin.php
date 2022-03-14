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
final class Plugin extends WPG\A6t\Plugin {
	/**
	 * Should setup hooks?
	 *
	 * @since 2021-12-15
	 *
	 * @return bool Should setup hooks?
	 */
	protected function should_setup_hooks() : bool {
		return parent::should_setup_hooks() && U\Env::is_litespeed();
	}

	/**
	 * Does hook setup on instantiation.
	 *
	 * @since 2021-12-15
	 */
	protected function setup_hooks() : void {
		parent::setup_hooks();

		// Right before WooCommerce fires: <https://git.io/JMmrj>.
		add_action( 'woocommerce_download_file_xsendfile', [ $this, 'on_woocommerce_download_file_xsendfile' ], 9, 2 );
	}

	/**
	 * Runs on `woocommerce_download_file_xsendfile` hook.
	 *
	 * @since 2021-12-15
	 *
	 * @param string $file_path File path.
	 * @param string $file_name File name.
	 */
	public function on_woocommerce_download_file_xsendfile( string $file_path, string $file_name ) : void {
		if ( ! U\Env::is_litespeed() ) {
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

		if ( ! empty( $parsed_file_path[ 'remote_file' ] ) ) {
			return; // Not possible. Pass back to WooCommerce core.
		}
		if ( empty( $parsed_file_path[ 'file_path' ] )
			|| ! is_file( $parsed_file_path[ 'file_path' ] )
			|| ! U\Fs::realize( $parsed_file_path[ 'file_path' ] )
		) {
			wc_get_logger()->warning(
				sprintf(
				/* translators: %1$s contains the filepath of the digital asset. */
					__( '%1$s could not be served using the X-LiteSpeed-Location method. Failed to parse `file_path`. Passing back to WooCommerce core.' ),
					$file_path
				)
			);
			return; // Not possible. Pass back to WooCommerce core.
		}
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
		$file_path             = U\Fs::realize( $file_path );
		$x_litespeed_base_path = U\Fs::realize( U\Env::var( 'DOCUMENT_ROOT' ) ?: ABSPATH );
		$x_litespeed_subpath   = U\Dir::subpath( $x_litespeed_base_path, $file_path, false );

		if ( ! $file_path || ! $x_litespeed_base_path || ! $x_litespeed_subpath ) {
			wc_get_logger()->warning(
				sprintf(
				/* translators: %1$s contains the filepath of the digital asset. */
					__( '%1$s could not be served using the X-LiteSpeed-Location method. Failed to acquire `x_litespeed_subpath`. Passing back to WooCommerce core.' ),
					$file_path
				)
			);
			return; // Not possible. Pass back to WooCommerce core.
		}
		$x_litespeed_location = '/' . $x_litespeed_subpath;
		$x_litespeed_location = $this->apply_filters(
			'download_file_x_litespeed_location',
			$x_litespeed_location,
			$file_path,
			$file_name,
			$parsed_file_path,
		);
		wc_get_logger()->debug(
			sprintf(
			/* translators: %1$s contains the filepath of the digital asset. */
				__( '%1$s is being served using the X-LiteSpeed-Location methodology.' ),
				$file_path
			)
		);
		$this->prep_file_download( $file_path, $file_name );
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
		U\HTTP::prep_for_file_download();

		header( 'content-description: File Transfer' );
		header( 'content-type: ' . U\File::mime_type( $file_path, 'application/force-download' ) );
		header( 'content-disposition: attachment; filename="' . str_replace( '"', '', $file_name ) . '";' );

		if ( $file_size = filesize( $file_path ) ) {
			header( 'content-length: ' . $file_size );
		}
	}
}
