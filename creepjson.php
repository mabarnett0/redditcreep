<?

class redditPage {
  public $url = '';
  private $ratelimit = 2000000; // 2 sec per Reddit API docs
  public $obj;
  public $posts = array();
  public $authors = array();
  public $userpage = 0;
  public $after = '';
  
  function __construct($url) {
    static $lastreq = 0;
    $this->url = $url;
    echo 'Loading ' . $url . "\n";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $this->url); // Set url and have curl return page contents on exec
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    
    if (($lastreq + 2000000) > microtime()) { // Delay if we have not waited rate limit
      usleep(($lastreq + 2000000) - microtime());
    }
    
    $this->obj = json_decode(curl_exec($curl));
    $lastreq = microtime();
    curl_close($curl);
  }
}

class subRedditPage extends redditPage {
  
  function parseNodes() {
    foreach($this->obj->data->children as $child) {
      $this->authors[] = $child->data->author;
      $this->posts[$child->data->name] = $child->data->title;
    }
    $this->authors = array_unique($this->authors);
    $this->after = $this->obj->data->after;
    unset($this->obj);
  }
  
}

class subReddit {
  public $scandepth;
  public $pages = array();
  public $url = '';
  
  function __construct($url, $scandepth) {
    $this->scandepth = $scandepth;
    $this->url = $url;
  }
  
  function scan() {
    $i = 0;
    $count = 0;
    while ($i < $this->scandepth) {
    	$this->pages[$i] = new subRedditPage(($i ? $this->url . '.json?count=' . $count . '&after=' . $this->pages[$i-1]->after : $this->url . '.json'));
    	$this->pages[$i]->parseNodes();
    	$count += count($this->pages[$i++]->posts);
    }
  }
  
}

$rp = new subReddit('www.reddit.com/r/Dallas/', 2);
$rp->scan();
print_r($rp);
?>