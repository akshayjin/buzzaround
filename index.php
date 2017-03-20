<?php
echo "<h2>Simple Twitter API Test</h2>";

require_once('TwitterAPIExchange.php');
 
// Get trends of place from Twitter Trend API if exists
$address = $_GET['message'];
// $address = "118c Pratap Nagar, Udaipur 313001 Rajasthan"; // Google HQ
$prepAddr = str_replace('-','+',str_replace('/',' ',str_replace(' ','+',$address)));
$xml=simplexml_load_file("http://where.yahooapis.com/v1/places.q('".$prepAddr."')?appid=<your-app-id>") or die("Error: Cannot create object");

echo "<br/>geodata ".$xml->place->woeid." ".$xml->place->centroid->latitude." ".$xml->place->centroid->longitude."<br/>";
$woeid = $xml->place->woeid;
$latitude = $xml->place->centroid->latitude;
$longitude = $xml->place->centroid->longitude;

$url = "https://api.twitter.com/1.1/trends/place.json";
$requestMethod = "GET";
$getfield = 'id='.$woeid;

$settings = array(
    'oauth_access_token' => "<twitter-oauth_access_token>",
    'oauth_access_token_secret' => "<twitter-oauth_access_token_secret>",
    'consumer_key' => "<twitter-consumer-key>",
    'consumer_secret' => "<twitter-consumer-secret>"
);
$twitter = new TwitterAPIExchange($settings);
$string = json_decode($twitter->setGetfield($getfield)
             ->buildOauth($url, $requestMethod)
             ->performRequest(),$assoc = TRUE);

$counter = 0;
$trends = array();
foreach($string[0]['trends'] as $items)
{
	if($counter==3)
		break;
	array_push($trends,$items['name']);
	echo $items['name']." ".$items['tweet_volume']."<br/>";
}
// print_r($string);
$trendsDetected = FALSE;

if (count($trends)>0)
	$trendsDetected == TRUE; 

// Get Tweets and analyze if no trends detected
if($trendsDetected==FALSE)
{
	
	// Get geolocation from address
	// $address = "118/c Pratap Nagar, Udaipur 313001 Rajasthan"; // Google HQ
	// $prepAddr = str_replace(' ','+',$address);
	// $geocode=file_get_contents('http://maps.google.com/maps/api/geocode/json?address='.$prepAddr.'&sensor=false');
	// $output= json_decode($geocode);
	// $latitude = $output->results[0]->geometry->location->lat;
	// $longitude = $output->results[0]->geometry->location->lng;
	
	echo "geolocation = ".$latitude .",".$longitude."<br/><br/>";
	
	$url = "https://api.twitter.com/1.1/search/tweets.json";
	$requestMethod = "GET";
	$radius = 5;
	$count = 10;
	$getfield = 'geocode='.$latitude .','.$longitude.','.$radius.'mi&count='.$count.'&lang=en';
	
	$string = json_decode($twitter->setGetfield($getfield)
	             ->buildOauth($url, $requestMethod)
	             ->performRequest(),$assoc = TRUE);
	
	if(isset($string["errors"])){
	if($string["errors"][0]["message"] != "") {echo "<h3>Sorry, there was a problem.</h3><p>Twitter returned the following error message:</p><p><em>".$string[errors][0]["message"]."</em></p>";exit();}}
	
	// print_r($string);
	$localTweets = "";
	foreach($string['statuses'] as $items)
    {
        // echo "Time and Date of Tweet: ".$items['created_at']."<br />";
        // echo "Tweet: ". $items  ['text']."<br />";
        // echo "Tweeted by: ". $items['user']['name']."<br />";
        // echo "Screen name: ". $items['user']['screen_name']."<br />";
        // echo "Followers: ". $items['user']['followers_count']."<br />";
        // echo "Friends: ". $items['user']['friends_count']."<br />";
        // echo "Listed: ". $items['user']['listed_count']."<br /><br />";
		$tweet = strtolower($items['text']);
		$tweet = str_replace("@ ", "@", $tweet);
		$tweet = str_replace("# ", "#", $tweet);
		$localTweets = $localTweets." ".$tweet;
    }
	
	// Remove filler words
	$allWords = explode(" ", $localTweets);
	
	$stopwords = array("a","about","above","after","again","against","all","am","an","and","any","are","aren't","as","at","be","because","been","before","being","below","between","both","but","by","can't",
	"cannot","could","couldn't","did","didn't","do","does","doesn't","doing","don't","down","during","each","few","for","from","further","had","hadn't","has","hasn't","have","haven't","having","he","he'd",
	"he'll","he's","her","here","here's","hers","herself","him","himself","his","how","how's","i","i'd","i'll","i'm","i've","if","in","into","is","isn't","it","it's","its","itself","just","let's","me","more","most",
	"mustn't","my","myself","no","nor","not","of","off","on","once","only","or","other","ought","our","ourstourselves","out","over","own","same","shan't","she","she'd","she'll","she's","should","shouldn't",
	"so","some","such","than","that","that's","the","their","theirs","them","themselves","then","there","there's","these","they","they'd","they'll","they're","they've","this","those","through","to","too",
	"under","until","up","very","was","wasn't","we","we'd","we'll","we're","we've","were","weren't","what","what's","when","when's","where","where's","which","while","who","who's","whom","why","why's","with",
	"won't","would","wouldn't","you","you'd","you'll","you're","you've","your","yours","yourself","yourselve");
	
	$diff = array_diff($allWords,$stopwords);
	// Identify people, events, hashtags
	$relevant_words_frequency = array();
	foreach($diff as $word)
	{
			if(strcmp(substr($word, 0,1),'#')==0 || strcmp(substr($word, 0,1),'@')==0 ){
				if (array_key_exists($word, $relevant_words_frequency))
					$relevant_words_frequency[$word]++;
				else 
					$relevant_words_frequency[$word]= 1;
			}
		
	}
	// Make a frequency array
	// Find top three trends
	array_multisort($relevant_words_frequency,SORT_NUMERIC, SORT_DESC);
	$counter = 0;
	foreach ($relevant_words_frequency as $word => $count) {
		if($counter==3)
			break;
		array_push($trends,$word);
	}
}

// Send back message



$message = "People near you are talking about ";
$counter  = 0;
foreach($trends as $trend)
{
		if ($counter==3)
			break;
		$message = $message.$trend.", ";
		$counter++;
		if ($counter==2)
			$message = $message."and ";
}

if (count($trends)==0)
	$message = "Sorry people are not tweeting enough in your area.";

echo $message."<br/>";
echo "phone no = ".$_GET['cid']."<br/>";
$url = 'http://www.kookoo.in/outbound/outbound_sms.php';
$param = array('api_key' => '<KooKoo api key>', 
'phone_no' => $_GET['cid'], 
'message' => $message
);
                          
$url = $url . "?" . http_build_query($param, '&');
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$result = curl_exec($ch);
curl_close($ch);
echo $result;

?>
