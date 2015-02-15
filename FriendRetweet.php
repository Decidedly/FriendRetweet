<?php

require 'vendor/autoload.php';

class FriendRetweet {
	// Settings
	var $twitterConsumerKey;
	var $twitterConsumerSecret;
	var $twitterAccessToken;
	var $twitterAccessTokenSecret;
	var $twitterUserId;
	var $memoryFilename;
	var $nativeRetweets;
	var $grabTweetsSinceLastRun;
	var $includeFriendRetweets;

	// State variables
	var $retweetThreshold;
	var $mostRecentTweetId;
	var $pastRetweets;

	function __construct() {
		$this->mostRecentTweetId = null;
		$this->pastRetweets = array();
		$this->grabTweetsSinceLastRun = true;
		$this->includeFriendRetweets = true;
	}


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

	function parseConfig($configFileName) {
		if(!file_exists($configFileName)) {
			echo "The config file {$configFile} does not exist.\n";
			exit(3);
		}

		$configJson = file_get_contents($configFileName);
		$config = json_decode($configJson);
		if($config == null) {
			echo "The config file is not a valid JSON file.\n";
			exit(3);
		} 

		$this->validateConfigContainsKeys(
			$config,
			array(
				"consumer_key", 
				"consumer_secret",
				"access_token",
				"access_token_secret",
				"twitter_user_id",
				"memory_filename",
				"retweet_threshold",
				"native_retweets"
			)
		);

		$this->twitterConsumerKey = $config->consumer_key;
		$this->twitterConsumerSecret = $config->consumer_secret;
		$this->twitterAccessToken = $config->access_token;
		$this->twitterAccessTokenSecret = $config->access_token_secret;
		$this->twitterUserId = $config->twitter_user_id;
		$this->memoryFilename = $config->memory_filename;
		$this->retweetThreshold = $config->retweet_threshold;
		$this->nativeRetweets = $config->native_retweets;

		if(isset($config->grab_tweets_since_last_run))
			$this->grabTweetsSinceLastRun = $config->grab_tweets_since_last_run;

		if(isset($config->include_friends_retweets))
			$this->includeFriendRetweets = $config->include_friends_retweets;
	
		return;
	}

	function validateConfigContainsKeys(\stdClass $config, array $keys) {
		$valid = true;
		foreach($keys as $key) {
			if(!isset($config->$key)) {
				echo "The config file is missing a '{$key}' setting.\n";
				$valid = false;
			}
		}

		if(!$valid) {
			exit(3);
		}

		return;
	}

	public function loadSavedState() {
		if (!file_exists($this->memoryFilename)) { 
			echo "No saved state file exists. Moving on...\n";
			return;
		}
	    
	    $buffer = file_get_contents($this->memoryFilename);
	    $memory = json_decode($buffer);

	    if(empty($memory) || !is_object($memory)) {
	    	echo "The saved state file is empty. Ignoring saved state.\n";
	    	return;
	    } 

	    if(!empty($memory->pastRetweets))
		    $this->pastRetweets = $memory->pastRetweets;

		if(!empty($memory->mostRecentTweetId))
		    $this->mostRecentTweetId = $memory->mostRecentTweetId;
	}

	public function grabTweets() {
		$tweets = array();
		$params = array(
			"count" => 200, 
			"trim_user" => true, 
			"lang" => "en",
			"exclude_replies" => true);

		$params['include_rts'] = $this->includeFriendRetweets;

		if(!empty($this->mostRecentTweetId) && $this->grabTweetsSinceLastRun) {
			$params["since_id"] = $this->mostRecentTweetId;
		}
		
		$searchResults = $this->twitter->get("statuses/home_timeline", $params);

		$counter = 0;
		
		while($searchResults && is_array($searchResults) && count($searchResults) > 0) {
			foreach($searchResults as $tweet) {
				if(defined('TWITTER_USER_ID')) {
					if($tweet->user->id == TWITTER_USER_ID) {
						if($this->verbose) {
							echo "Skipping our own tweet (user id {$tweet->user->id} matches ". TWITTER_USER_ID . "): " . $tweet->text . "\n";
						}
						continue;
					}
				}

				if($this->verbose) {
					echo $text . "\n";
				}
				
				$tweets[] = $tweet;
				$counter++;
				$this->mostRecentTweetId = max($tweet->id, $this->mostRecentTweetId);
				$lastTweetId = $tweet->id;
			}
			$params['max_id'] = $lastTweetId - 1;
			$searchResults = $this->twitter->get("statuses/home_timeline", $params);
		}

		return $tweets;
	}

	public function pickTweet($tweets) {
		$winningTweet = null;

		foreach($tweets as $tweet) {
			if(in_array($tweet->id, $this->pastRetweets)) {
				continue;
			}

			if($winningTweet == null) {
				$winningTweet = $tweet;
				continue;
			}

			if($tweet->retweet_count > $winningTweet->retweet_count) {
				$winningTweet = $tweet;
			}
		}

		return $winningTweet;
	}

	public function retweet($tweet) {
		if($this->nativeRetweets) {
			$result = $this->twitter->post("statuses/retweet/" . $tweet->id, array());
		} else {
			$result = $this->twitter->post("statuses/update", array("status" => $tweet->text));
		}

		return $result;
	}

	public function saveState() {
		$memory = new \stdClass;
		$memory->pastRetweets = $this->pastRetweets;
		$memory->mostRecentTweetId = $this->mostRecentTweetId;

		while(count($memory->pastRetweets) > 100) {
			array_shift($memory->pastRetweets);
		}
	
		$success = file_put_contents($this->memoryFilename, json_encode($memory));

		if(!$success) {
			echo "Unable to save memory file.";
			exit(4);
		}
	}

	public function run() {
		// Grab CLI args
		$arguments = $this->parseCommandLineArguments();

		// Set settings based on args
		$this->verbose = $arguments['verbose'];
		$configFileName = $arguments['config'];

		// Parse the config
		$this->parseConfig($configFileName);

		$this->twitter = new Abraham\TwitterOAuth\TwitterOAuth(
			$this->twitterConsumerKey, 
			$this->twitterConsumerSecret, 
			$this->twitterAccessToken, 
			$this->twitterAccessTokenSecret);

		$this->loadSavedState();

		$tweets = $this->grabTweets();
		echo "Grabbed " . count($tweets) . " tweets.\n";

		if(count($tweets) == 0) {
			echo "Exiting because no tweets.\n";
			exit(5);
		}

		$tweet = $this->pickTweet($tweets);
		if(empty($tweet)) {
			echo "No tweets.\n";
			exit(5);
		}
		echo "Chose Tweet " . $tweet->text . " which has {$tweet->retweet_count} retweets.\n";

		$result = $this->retweet($tweet, $this->nativeRetweets);
		if(empty($result->errors)) {
			echo "Retweet successful.\n";
			$this->pastRetweets[] = $tweet->id;
		}

		$this->saveState();
	}

}


$app = new FriendRetweet();
$app->run();