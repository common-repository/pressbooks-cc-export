<?php
/**
 * Plugin Name:     CC Export for Pressbooks
 * Plugin URI:      https://github.com/bccampus/pressbooks-cc-export
 * Description:     Common Cartridge Export for Pressbooks
 * Author:          BCcampus
 * Author URI:      https://github.com/BCcampus
 * Text Domain:     pressbooks-cc-export
 * Domain Path:     /languages
 * Version:         1.1.1
 * License:         GPL-3.0+
 * Tags: pressbooks, OER, publishing, common cartridge, imscc
 * Network: True
 * Pressbooks tested up to: 5.7.0
 *
 * @package         Pressbooks_Cc_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Minimum requirements before either PB or PCE objects are instantiated
|--------------------------------------------------------------------------
|
|
|
|
*/
add_action(
	'init', function () {
		$min_pb_compatibility_version = '5.6.0';

		if ( ! include_once( WP_PLUGIN_DIR . '/pressbooks/compatibility.php' ) ) {
			add_action(
				'admin_notices', function () {
					echo '<div id="message" class="error fade"><p>' . __( 'CC Export cannot find a Pressbooks install.', 'pressbooks-cc-export' ) . '</p></div>';
				}
			);

			return;
		}

		if ( function_exists( 'pb_meets_minimum_requirements' ) ) {
			if ( ! pb_meets_minimum_requirements() ) {
				// This PB function checks for both multisite, PHP and WP minimum versions.
				add_action(
					'admin_notices', function () {
						echo '<div id="message" class="error fade"><p>' . __( 'Your PHP version may not be supported by PressBooks.', 'pressbooks-cc-export' ) . '</p></div>';
					}
				);

				return;
			}
		}

		if ( ! version_compare( PB_PLUGIN_VERSION, $min_pb_compatibility_version, '>=' ) ) {
			add_action(
				'admin_notices', function () {
					echo '<div id="message" class="error fade"><p>' . __( 'CC Export for Pressbooks requires Pressbooks 5.6.0 or greater.', 'pressbooks-cc-export' ) . '</p></div>';
				}
			);

			return;
		}

	}
);

/*
|--------------------------------------------------------------------------
| autoload classes
|--------------------------------------------------------------------------
|
|
|
|
*/
if ( function_exists( '\HM\Autoloader\register_class_path' ) ) {
	\HM\Autoloader\register_class_path( 'BCcampusCC', __DIR__ . '/inc' );
}

// Load Composer Dependencies
$composer = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer ) ) {
	require_once( $composer );
}

/*
|--------------------------------------------------------------------------
| Hook into the pb matrix
|--------------------------------------------------------------------------
|
|
|
|
*/
add_filter(
	'pb_export_formats', function ( $formats ) {
		$formats['exotic']['imscc11'] = __( 'Common Cartridge (v1.1)', 'pressbooks-cc-export' );

		return $formats;
	}
);

add_filter(
	'pb_active_export_modules', function ( $modules ) {
		if ( isset( $_POST['export_formats']['imscc11'] ) ) { // @codingStandardsIgnoreLine
			$modules[] = '\BCcampusCC\Export\CC\Imscc11';
		}

		return $modules;

	}
);


/*
|--------------------------------------------------------------------------
| Add imscc export format to the latest exports list on front page of a book
|--------------------------------------------------------------------------
|
|
|
|
*/
add_filter(
	'pb_latest_export_filetypes', function ( $filetypes ) {
		$filetypes['imscc11'] = '.imscc';

		return $filetypes;
	}
);

add_filter(
	'pb_export_filetype_names', function ( $array ) {

		if ( ! isset( $array['imscc11'] ) ) {
			$array['imscc11'] = __( 'Common Cartridge', 'pressbooks-cc-export' );
		}

		return $array;
	}
);

/*
|--------------------------------------------------------------------------
| Add imscc icon to front page of a book
|--------------------------------------------------------------------------
|
|
|
|
*/
add_action(
	'wp_enqueue_scripts', function () {
		// Load only on front page
		if ( is_front_page() ) {
			wp_enqueue_style( 'fp_icon_style', plugins_url( 'assets/styles/fp-icon-style.css', __FILE__ ) );
		}

		return;
	}
);

/*
|--------------------------------------------------------------------------
| Add imscc icon to the admin PB export page
|--------------------------------------------------------------------------
|
|
|
|
*/
add_action(
	'admin_enqueue_scripts', function ( $hook ) {
		// Load only on export page
		if ( $hook !== 'toplevel_page_pb_export' ) {
			return;
		}
		wp_enqueue_style( 'cc_icon_style', plugins_url( 'assets/styles/cc-icon-style.css', __FILE__ ) );
	}
);
