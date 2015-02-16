<?php

namespace Decidedly;

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
	var $mostRecentTweetId;
	var $pastRetweets;
	var $verbose;

	function __construct() {
		$this->mostRecentTweetId = null;
		$this->pastRetweets = array();
		$this->grabTweetsSinceLastRun = true;
		$this->includeFriendRetweets = true;
		$this->verbose = false;
	}

	function parseConfig($configFileName) {
		if(!file_exists($configFileName)) {
			throw new \Exception("The config file {$configFile} does not exist.");
		}

		$configJson = file_get_contents($configFileName);
		$config = json_decode($configJson);
		if($config == null) {
			throw new \Exception("The config file is not a valid JSON file.");
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
				"native_retweets"
			)
		);

		$this->twitterConsumerKey = $config->consumer_key;
		$this->twitterConsumerSecret = $config->consumer_secret;
		$this->twitterAccessToken = $config->access_token;
		$this->twitterAccessTokenSecret = $config->access_token_secret;
		$this->twitterUserId = $config->twitter_user_id;
		$this->memoryFilename = $config->memory_filename;
		$this->nativeRetweets = $config->native_retweets;

		if(isset($config->grab_tweets_since_last_run))
			$this->grabTweetsSinceLastRun = $config->grab_tweets_since_last_run;

		if(isset($config->include_friends_retweets))
			$this->includeFriendRetweets = $config->include_friends_retweets;
	
		return;
	}

	function validateConfigContainsKeys(\stdClass $config, array $keys) {
		$valid = true; $errors = array();
		foreach($keys as $key) {
			if(!isset($config->$key)) {
				$errors[] = "The config file is missing a '{$key}' setting.";
				$valid = false;
			}
		}

		if(!$valid) {
			throw new \Exception(implode('\n',$errors));
		}
	}

	function saveConfig($configFileName) {
		$configObject = new \stdClass;

		$configObject->consumer_key = $this->twitterConsumerKey;
		$configObject->consumer_secret = $this->twitterConsumerSecret;
		$configObject->access_token = $this->twitterAccessToken;
		$configObject->access_token_secret = $this->twitterAccessTokenSecret;
		$configObject->twitter_user_id = $this->twitterUserId;
		$configObject->memory_filename = $this->memoryFilename;
		$configObject->native_retweets = $this->nativeRetweets;
		$configObject->grab_tweets_since_last_run = $this->grabTweetsSinceLastRun;
		$configObject->include_friends_retweets = $this->includeFriendRetweets;

		$configJson = json_encode($configObject, JSON_PRETTY_PRINT);
		$result = file_put_contents($configFileName, $configJson);

		return $result;
	}

	public function loadSavedState() {
		if (!file_exists($this->memoryFilename)) { 
			if($this->verbose) {
				echo "No saved state file exists. Moving on...\n";
			}
			return;
		}
	    
	    $buffer = file_get_contents($this->memoryFilename);
	    $memory = json_decode($buffer);

	    if(empty($memory) || !is_object($memory)) {
	    	if($this->verbose) {
		    	echo "The saved state file is empty. Ignoring saved state.\n";
		    }
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
			throw new \Exception("Unable to save memory file.");
		}
	}

	public function run() {
		$this->twitter = new \Abraham\TwitterOAuth\TwitterOAuth(
			$this->twitterConsumerKey, 
			$this->twitterConsumerSecret, 
			$this->twitterAccessToken, 
			$this->twitterAccessTokenSecret);

		$this->loadSavedState();

		$tweets = $this->grabTweets();
		if($this->verbose) 
			echo "Grabbed " . count($tweets) . " tweets.\n";

		if(count($tweets) == 0) {
			if($this->verbose)
				echo "Exiting because no tweets.\n";
			return;
		}

		$tweet = $this->pickTweet($tweets);
		if(empty($tweet)) {
			if($this->verbose)
				echo "No tweet picked.\n";
			return;
		}

		if($this->verbose) 
			echo "Chose Tweet " . $tweet->text . " which has {$tweet->retweet_count} retweets.\n";

		$result = $this->retweet($tweet, $this->nativeRetweets);
		if(empty($result->errors)) {
			if($this->verbose)
				echo "Retweet successful.\n";
			$this->pastRetweets[] = $tweet->id;
		}

		$this->saveState();
	}
}