<?php

require_once 'formatter.php';
use Helpers\Formatter\GitHubEventFormatter;

$command = $argv[0];
$username = (string)$argv[1];
const TTL_SECONDS = 200;


// print($command . "\n");
// print($username . "\n");

function fetchGithubAccountData(string $username) {

  // Agregamos cache para no tener que hacer la request al pedo
  $cachedData = getCachedInstance($username);

  if ($cachedData !== null) return $cachedData;

  $ghApiEndpoint = "https://api.github.com/users/$username/events";
  $session = curl_init();
  $headers = [
    'User-Agent: ghcli'
  ];

  curl_setopt($session, CURLOPT_URL, $ghApiEndpoint);
  curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($session);
  file_put_contents('./cache_' . $username, $response);
  return $response;
}

function getCachedInstance(string $username)
{
  
  if(file_exists('./cache_'. $username))
  {
    $timeOfTheFile = time() - filemtime('./cache_'. $username);

    if($timeOfTheFile > TTL_SECONDS)
      {
        print("CACHE EXPIRED\n");
        return null;
      }
      
    print("CACHE HIT");
    $cacheData = file_get_contents('./cache_'. $username);
    if(!$cacheData)
      {
      print("CACHE WITH NO DATA\n");
      return null;
      } 
    return $cacheData;
  }
  print("CACHE MISS\n");
  return null;
}


function parseGhData($data)
{
  $formatter = new GitHubEventFormatter();
  $curatedData = $formatter->format($data);;

  foreach($curatedData as $item){    
    if($item != null){
      echo "- $item\n";
    }
  }
}

$data = fetchGithubAccountData($username);
$parsedData = json_decode($data, true);
parseGhData($parsedData);