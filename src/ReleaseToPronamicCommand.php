<?php
/**
 * Release to Pronamic command
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2018 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\Deployer
 */

namespace Pronamic\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Release to Pronamic command class
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class ReleaseToPronamicCommand extends Command {
	/**
	 * Configure.
	 */
	protected function configure() {
		$this
			->setName( 'release-to-pronamic' )
			->setDescription( 'Release to Pronamic.' )
			->setDefinition(
				new InputDefinition(
					[
						new InputOption(
							'working-dir',
							null,
							InputOption::VALUE_REQUIRED,
							'The working directory.',
							'./'
						),
						new InputOption(
							'build-dir',
							null,
							InputOption::VALUE_REQUIRED,
							'The build directory.',
							'./build/project'
						),
					]
				)
			);
	}

	/**
	 * Execute.
	 *
	 * @param InputInterface  $input  Input interface.
	 * @param OutputInterface $output Output interface.
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$io = new SymfonyStyle( $input, $output );

		$helper = $this->getHelper( 'process' );

		$io->title( 'Pronamic release' );

		// Input.
		$working_dir = $input->getOption( 'working-dir' );
		$build_dir   = $input->getOption( 'build-dir' );

		// Project.
		$project = new WpProject( $working_dir );

		$slug      = $project->get_slug();
		$type      = $project->get_type();
		$version   = $project->get_version();
		$changelog = $project->get_changelog();

		if ( null === $slug ) {
			$io->error( 'No slug.' );

			return 1;
		}

		if ( null === $version ) {
			$io->error( 'No version.' );

			return 1;
		}

		if ( ! in_array( $type, [ 'plugin', 'theme' ], true ) ) {
			$io->error( 'Invalid type.' );

			return 1;
		}

		// Distribution archive.
		$zip_file_path = Path::makeRelative(
			$build_dir . '/../' . $slug . '.' . $version . '.zip',
			\getcwd()
		);

		// Google Cloud Storage.
		switch ( $type ) {
			case 'plugin':
				$gcloud_bucket_name = 'gs://wp.pronamic.download/plugins/' . $slug;
				break;
			case 'theme':
				$gcloud_bucket_name = 'gs://wp.pronamic.download/themes/' . $slug;
				break;
		}

		$io->section( 'Google Cloud Storage' );

		$zip_filename_version = "$slug.$version.zip";

		$zip_filename = "$slug.zip";

		$command = [
			'gcloud',
			'storage',
			'cp',
			$zip_file_path,
			$gcloud_bucket_name . '/' . $zip_filename_version,
		];

		$process = new Process( $command );

		$helper->mustRun( $output, $process );

		$command = [
			'gcloud',
			'storage',
			'cp',
			$gcloud_bucket_name . '/' . $zip_filename_version,
			$gcloud_bucket_name . '/' . $zip_filename,
		];

		$process = new Process( $command );

		$helper->mustRun( $output, $process );

		// Pronamic.directory.
		$io->section( 'Pronamic.directory' );

		switch ( $type ) {
			case 'plugin':
				$pronamic_url = 'https://wp.pronamic.directory/wp-json/pronamic-wp-extensions/v1/plugins/' . $slug;
				break;
			case 'theme':
				$pronamic_url = 'https://wp.pronamic.directory/wp-json/pronamic-wp-extensions/v1/themes/' . $slug;
				break;
		}

		$command = [
			'curl',
			'--netrc',
			'--data',
			'version=' . $version,
			'--request',
			'PATCH',
			$pronamic_url,
		];

		$process = new Process( $command );

		$helper->mustRun( $output, $process );

		return 0;
	}
}
