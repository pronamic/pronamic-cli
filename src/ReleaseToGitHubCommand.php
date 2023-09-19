<?php
/**
 * Release to GitHub command
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
 * Release to GitHub command class
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class ReleaseToGitHubCommand extends Command {
	/**
	 * Configure.
	 */
	protected function configure() {
		$this
			->setName( 'release-to-github' )
			->setDescription( 'Release to GitHub.' )
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

		// Input.
		$working_dir = $input->getOption( 'working-dir' );
		$build_dir   = $input->getOption( 'build-dir' );

		// Project.
		$project = new WpProject( $working_dir );

		$slug      = $project->get_slug();
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

		// Release.
		$io->title( 'GitHub release' );

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

			return 0;
		}

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

		return 0;
	}
}
