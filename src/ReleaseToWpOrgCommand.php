<?php
/**
 * Release to WordPress.org command
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2018 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Deployer
 */

namespace Pronamic\CLI;

use Acme\Command\DefaultCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Release to WordPress.org command class
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class ReleaseToWpOrgCommand extends Command {
	/**
	 * Configure.
	 */
	protected function configure() {
		$this
			->setName( 'release-to-wp-org' )
			->setDescription( 'Release to WordPress.org.' )
			->setDefinition(
				new InputDefinition(
					[
						new InputOption(
							'working-dir',
							null,
							InputOption::VALUE_REQUIRED,
							'The working directory.',
							'./build/project'
						),
						new InputOption(
							'svn-dir',
							null,
							InputOption::VALUE_REQUIRED,
							'The build directory.',
							'./build/svn'
						),
					]
				)
			);
	}

	/**
	 * Execute.
	 * 
	 * @param InputInterface  $input   Input interface.
	 * @param OutputInterface $output Output interface.
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$io = new SymfonyStyle( $input, $output );

		$working_dir = $input->getOption( 'working-dir' );
		$svn_dir     = $input->getOption( 'svn-dir' );

		// Project.
		$project = new WpProject( $working_dir );

		$slug = $project->get_slug();
		$type = $project->get_type();

		if ( null === $slug ) {
			$io->error( 'No slug.' );

			return 1;
		}

		if ( 'plugin' !== $type ) {
			$io->error( 'Invalid type.' );

			return 1;
		}

		$svn_url = 'https://plugins.svn.wordpress.org/' . $slug;

		$file_headers = new FileHeaders();

		$plugins = [];

		foreach ( \glob( $working_dir . '/*.php' ) as $file ) {
			$headers = $file_headers->get_headers( $file );

			if ( \array_key_exists( 'Plugin Name', $headers ) ) {
				$plugins[ $file ] = $headers;
			}
		}

		if ( 1 !== count( $plugins ) ) {
			$io->error( 'Could not find WordPress plugin.' );

			return 1;
		}

		$version = null;

		foreach ( $plugins as $file => $headers ) {
			if ( \array_key_exists( 'Version', $headers ) ) {
				$version = $headers['Version'];
			}
		}

		if ( empty( $version ) ) {
			$io->error( 'No version in plugin header.' );

			return 1;
		}

		$command = $this->getApplication()->find( 'release-to-svn' );

		$command_arguments = [
			'working-dir' => $working_dir,
			'svn-dir'     => $svn_dir,
			'svn-url'     => $svn_url,
			'version'     => $version,
			'--username'  => \getenv( 'WP_ORG_USERNAME' ),
			'--password'  => \getenv( 'WP_ORG_PASSWORD' ),
		];

		$command_input = new ArrayInput( $command_arguments );

		return $command->run( $command_input, $output );
	}
}
