<?php
namespace Deployer;

//adds common necessities for the deployment
require 'recipe/common.php';

set('ssh_type', 'native');
set('ssh_multiplexing', true);

if (file_exists('vendor/deployer/recipes/recipe/rsync.php')) {
	require 'vendor/deployer/recipes/recipe/rsync.php';
} else {
	require getenv('COMPOSER_HOME') . '/vendor/deployer/recipes/recipe/rsync.php';
}

set('shared_dirs', ['wp-content/uploads']);
set('writable_dirs', [
	'wp-content',
	'wp-content/uploads',
]);
inventory('/hosts.yml');

$deployer = Deployer::get();
$hosts = $deployer->hosts;

foreach ($hosts as $host) {
	$host
	->addSshOption('UserKnownHostsFile', '/dev/null')
	->addSshOption('StrictHostKeyChecking', 'no');

	$deployer->hosts->set($host->getHostname(), $host);
}

// Add tests and other directory uncessecary for
// production to exclude block.
set('rsync', [
	'exclude'      => [
		'.git',
		'.github',
		'deploy.php',
		'composer.lock',
		'.env',
		'.env.example',
		'.gitignore',
		'.gitlab-ci.yml',
		'Gruntfile.js',
		'package.json',
		'README.md',
		'gulpfile.js',
		'.circleci',
		'package-lock.json',
		'package.json',
		'phpcs.xml'
	],
	'exclude-file' => true,
	'include'      => [],
	'include-file' => false,
	'filter'       => [],
	'filter-file'  => false,
	'filter-perdir'=> false,
	'flags'        => 'rz', // Recursive, with compress
	'options'      => [ 'delete', 'delete-excluded', 'links', 'no-perms', 'no-owner', 'no-group' ],
	'timeout'      => 300,
]);
set('rsync_src', getenv('build_root'));
set('rsync_dest', '{{release_path}}');


/*  custom task defination    */
desc('Download cachetool');
task('cachetool:download', function () {
	run('wget https://raw.githubusercontent.com/gordalina/cachetool/gh-pages/downloads/cachetool-3.0.0.phar -O {{release_path}}/cachetool.phar');
});

desc('Symlink wp-config.php');
task('wp:config', function () {
	run('[ ! -f {{release_path}}/../wp-config.php ] && cd {{release_path}}/../ && ln -sn ../wp-config.php && echo "Created Symlink for wp-config.php." || echo ""');
});

/*
 * Change permissions to 'www-data' for 'current/',
 * so that 'wp-cli' can read/write files.
 */
desc('Correct Permissions');
task('permissions:set', function () {
	$output = run('chown -R www-data:www-data {{deploy_path}}');
	writeln('<info>' . $output . '</info>');
});

/*   deployment task   */
desc('Deploy the project');
task('deploy', [
	'deploy:prepare',
	'deploy:unlock',
	'deploy:lock',
	'deploy:release',
	'rsync',
	'wp:config',
	'cachetool:download',
	'deploy:shared',
	'deploy:symlink',
	'permissions:set',
	'deploy:unlock',
	'cleanup'
]);
after('deploy', 'success');
