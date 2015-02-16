# FriendRetweet
**FriendRetweet** is a command-line PHP script that allows you to automatically retweet the most popular tweets of the people you're following. Each time the app is run, it will scan your home timeline and retweet the most popular tweet since the last time it was run. Settings options exist to allow it to use old-school retweets instead of new retweets, as well as to go back as far in time as Twitter API will let us.

## Download

Download the script here: https://github.com/Decidedly/FriendRetweet

## Installation
1. Create a Twitter app for yourself at https://aps.twitter.com. Make sure you create an access token and set your permissions to read-write as well.
2. `php composer install`
3. `cp config/example.config config/username.config`
4. Configure the app as follows:
     
    | Config Key     | Description                  |
    | ----------------------------------------------|
    | `consumer_key` | Your Twitter consumer key |
    | `consumer_secret` | Your Twitter consumer secret |
    | `access_token` | Your Twitter access token |
    | `access_token_secret` | Your Twitter access token secret |
    | `twitter_user_id` | Your numeric Twitter User id. |
    | `memory_filename` | A path to the file where your user's data will be stored. These files don't generally get very big. |
    | `native_retweets` | A value of **true** causes native tweets, a values of **false** causes us to simply tweet the same text as the source tweet. |
    | `grab_tweets_since_last_run` | A value of **true** causes only tweets since the last time the app was run to be considered for retweets. Otherwise, all tweets in timeline will be considered. |
    | `include_friends_retweets` | A value of **false** causes only tweets by people we follow to be considered, but does exclude items that they themselves retweeted (ie things that they didn't post themselves). |

## Running the App

From the command-line, type this:

`php FriendRetweet.php --config configs/username.config`
