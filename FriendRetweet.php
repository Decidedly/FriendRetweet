<?php

require 'vendor/autoload.php';

function parseCommandLineArguments() {
	$arguments = new \cli\Arguments();

	$arguments->addFlag(array('verbose', 'v'), 'Turn on verbose output');
	$arguments->addFlag(array('help', 'h'), 'Show this help screen');
	$arguments->addOption(array('config', 'c'), array(
		'default'     => 'settings.config',
		'description' => 'Specify the config file to use'));

	$arguments->parse();
	if ($arguments['help']) {
		echo $arguments->getHelpScreen();
		echo "\n\n";
	}

	if(empty($arguments['config'])) {
		$arguments['config'] = 'settings.config';
		echo "You must specify a config file.\n\n";
		echo $arguments->getHelpScreen();
		echo "\n\n";
		exit(1);
	}

	return $arguments;
}

// Grab CLI args
$arguments = parseCommandLineArguments();

// Set settings based on args
$configFileName = $arguments['config'];

$friendRetweet = new \Decidedly\FriendRetweet();
$friendRetweet->verbose = $arguments['verbose'];

try {
	$friendRetweet->parseConfig($configFileName);
} catch(\Exception $e) {
	echo $e->getMessage() . "\n";
	exit(2);
}

$friendRetweet->run();