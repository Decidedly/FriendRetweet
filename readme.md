# FriendRetweet
Automatically retweet the most popular tweets of the people we're following. Each time it is run, the app will scan your users home timeline and find the most popular tweet since the last time it was run. It will then retweet that tweet.

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

## Running the App

From the command-line, type this:

`php FriendRetweet.php --config configs/username.config`