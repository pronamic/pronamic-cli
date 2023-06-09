<?php
/**
 * Release command
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
 * Release command
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class ReleaseCommand extends Command {
	/**
	 * Configure.
	 */
	protected function configure() {
		$this
			->setName( 'release' )
			->setDescription( 'Release.' )
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
						new InputOption(
							'svn-dir',
							null,
							InputOption::VALUE_REQUIRED,
							'The Subversion directory.',
							'./build/svn'
						),
						new InputOption(
							'gcloud-storage',
							null,
							InputOption::VALUE_NONE,
							'Release to Google Cloud Storage?'
						),
						new InputOption(
							'pronamic-directory',
							null,
							InputOption::VALUE_NONE,
							'Release to Pronamic.directory?'
						),
						new InputOption(
							'github',
							null,
							InputOption::VALUE_NONE,
							'Release to GitHub?'
						),
						new InputOption(
							'wp-org',
							null,
							InputOption::VALUE_NONE,
							'Release to WordPress.org?'
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

		$io->title( 'Release' );

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

		// Distribution archive.
		$zip_file_path = Path::makeRelative(
			$build_dir . '/../' . $slug . '.' . $version . '.zip',
			\getcwd()
		);

		// Build.
		$io->section( 'Build' );

		$command = $this->getApplication()->find( 'wp-build' );

		$command_arguments = [
			'--working-dir' => $working_dir,
			'--build-dir'   => $build_dir,
		];

		$command_input = new ArrayInput( $command_arguments );

		$command->run( $command_input, $output );

		// Google Cloud Storage.
		$release_to_gcloud_storage = $input->getOption( 'gcloud-storage' );

		$gcloud_bucket_name = null;

		switch ( $type ) {
			case 'plugin':
				$gcloud_bucket_name = 'gs://wp.pronamic.download/plugins/' . $slug;
				break;
			case 'theme':
				$gcloud_bucket_name = 'gs://wp.pronamic.download/themes/' . $slug;
				break;
		}

		if ( $release_to_gcloud_storage && $gcloud_bucket_name ) {
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
		}

		// Pronamic.directory.
		$release_to_pronamic_directory = $input->getOption( 'pronamic-directory' );

		$pronamic_url = null;

		switch ( $type ) {
			case 'plugin':
				$pronamic_url = 'https://wp.pronamic.directory/wp-json/pronamic-wp-extensions/v1/plugins/' . $slug;
				break;
			case 'theme':
				$pronamic_url = 'https://wp.pronamic.directory/wp-json/pronamic-wp-extensions/v1/themes/' . $slug;
				break;
		}

		if ( $release_to_pronamic_directory && $pronamic_url ) {
			$io->section( 'Pronamic.directory' );

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
		}

		// GitHub.
		$release_to_github = $input->getOption( 'github' );

		if ( $release_to_github ) {
			$io->section( 'GitHub' );

			$command = [
				'gh',
				'release',
				'view',
				'v' . $version,
				'--json',
				'url',
			];

			$process = new Process( $command );

			$helper->run( $output, $process );

			if ( $process->isSuccessful() ) {
				$io->text( 'GitHub release already exists.' );
			}

			if ( ! $process->isSuccessful() ) {
				$command = [
					'gh',
					'release',
					'create',
					'v' . $version,
					'--title',
					$version,
					'--notes-file',
					'-',
					$zip_file_path,
				];

				$changelog_entry = '';

				$entry = $changelog->get_entry( $version );

				if ( null !== $entry ) {
					$changelog_entry = $entry->body;
				}

				$process = new Process( $command, null, null, $changelog_entry, null );

				$helper->mustRun( $output, $process );
			}
		}

		// WordPress.org.
		$release_to_wp_org = $input->getOption( 'wp-org' );

		if ( $release_to_wp_org ) {
			$io->section( 'WordPress.org' );

			$svn_dir = $input->getOption( 'svn-dir' );

			$command = $this->getApplication()->find( 'wp-org-release' );

			$command_arguments = [
				'working-dir' => $build_dir,
				'svn-dir'     => $svn_dir,
				'slug'        => $slug,
			];

			$command_input = new ArrayInput( $command_arguments );

			$command->run( $command_input, $output );
		}

		return 0;
	}
}
