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
  public $posts = array();
  
  function parseNodes() {
    foreach($this->obj->data->children as $child) {
      $this->authors[] = $child->data->author;
      $this->posts[$child->data->name] = $child->data->title;
    }
    $this->authors = array_unique($this->authors);
    $this->after = $this->obj->data->after;
    unset($this->obj);
  }
  
  function parsePosts() {
  	foreach ($this->posts as $key => $post) {
  		$postId = substr($key, 3);
  		$this->posts[$postId] = new commentPage('www.reddit.com/comments/' . $postId . '.json');
  		$this->posts[$postId]->parseNodes();
  		$this->authors = array_merge($this->authors, $this->posts[$postId]->authors);
  	}
    $this->authors = array_unique($this->authors);
  }
  
}

class commentPage extends redditPage {
	
	function parseNodes() {
    foreach($this->obj[1]->data->children as $child) {
      $this->parseComment($child);
    }
  }
  
  function parseComment($comment) {
  	$this->authors[] = $comment->data->author;
  	if ($comment->data->replies) {
  		foreach ($comment->data->replies->data->children as $subComment) {
  			$this->parseComment($subComment);
  		}
  	}
    $this->authors = array_unique($this->authors);
  }
	
}

class userPage extends redditPage {
	
	
	
}

class subReddit {
  public $scandepth;
  public $pages = array();
  public $url = '';
  public $authors = array();
  
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
    	$this->pages[$i]->parsePosts();
    	$this->authors = array_merge($this->authors, $this->pages[$i]->authors);
    	$count += count($this->pages[$i++]->posts);
    }
    $this->authors = array_unique($this->authors);
  }
  
}

$rp = new subReddit('www.reddit.com/r/Dallas/', 1);
$rp->scan();
print_r($rp->authors);
?>