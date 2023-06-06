<?php
/**
 * WordPress project
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2018 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Deployer
 */

namespace Pronamic\CLI;

/**
 * WordPress project
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class WpProject {
	/**
	 * Directory.
	 * 
	 * @var string
	 */
	private $directory;

	/**
	 * Construct WordPress project.
	 * 
	 * @param string $directory Directory.
	 */
	public function __construct( $directory ) {
		$this->directory = $directory;
	}

	/**
	 * Get slug.
	 * 
	 * @return null|string
	 */
	public function get_slug() {
		$slug = null;

		$composer_json_file = $this->directory . '/composer.json';

		if ( \is_readable( $composer_json_file ) ) {
			$data = \file_get_contents( $composer_json_file );

			$composer_json = \json_decode( $data );

			if ( isset( $composer_json->config->{'wp-slug'} ) ) {
				$slug = $composer_json->config->{'wp-slug'};
			}
		}

		return $slug;
	}

	/**
	 * Get type.
	 * 
	 * @return null|string
	 */
	public function get_type() {
		$type = null;

		$composer_json_file = $this->directory . '/composer.json';

		if ( \is_readable( $composer_json_file ) ) {
			$data = \file_get_contents( $composer_json_file );

			$composer_json = \json_decode( $data );

			if ( isset( $composer_json->type ) ) {
				switch ( $composer_json->type ) {
					case 'wordpress-plugin':
						$type = 'plugin';
						break;
					case 'wordpress-theme':
						$type = 'theme';
						break;
				}
			}
		}

		return $type;
	}

	/**
	 * Get version.
	 * 
	 * @return null|string
	 */
	public function get_version() {
		$version = null;

		$package_json_file = $this->directory . '/package.json';

		if ( \is_readable( $package_json_file ) ) {
			$data = \file_get_contents( $package_json_file );

			$package_json = \json_decode( $data );

			if ( isset( $package_json->version ) ) {
				$version = $package_json->version;
			}
		}

		return $version;
	}

	/**
	 * Get changelog.
	 * 
	 * @return Changelog
	 */
	public function get_changelog() {
		$changelog_file = $this->directory . '/CHANGELOG.md';

		$changelog = new Changelog( $changelog_file );

		return $changelog;
	}
}
