<?

class redditPage {
  public $url = '';
  private $ratelimit = 2000000; // 2 sec per Reddit API docs
  public $obj;
  public $posts = array();
  public $authors = array();
  public $comments = array();
  public $after = '';
  public $needles = array();
  public $finds = array();
  public $subreddits = array();
  
  function __construct($url, $needles = array()) {
    static $lastreq = 0;
    $this->url = $url;
    $this->needles = $needles;
    echo 'Loading ' . $url . "\n";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $this->url); // Set url and have curl return page contents on exec
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    
    if (($lastreq + $this->ratelimit) > microtime()) { // Delay if we have not waited rate limit
      usleep(($lastreq + $this->ratelimit) - microtime());
    }
    
    $this->obj = json_decode(curl_exec($curl));
    $lastreq = microtime();
    curl_close($curl);
  }
  
  function parseObj($obj = 0, $top = 1) {
  	if (!$obj) {
  		$obj = $this->obj;
  	}
  	foreach ($obj as $subObj) {
  		$this->parseShallowObj($subObj);
  	}
  }
  
  function parseShallowObj($obj = 0, $top = 1) {
  	if (!$obj) {
  		$obj = $this->obj;
  	}
  	foreach ($obj->data->children as $child) { // Some objects still acting strange, check for kind == t1/t3 maybe?
  		if (isset($child->data) && $child->kind != 'more') { // Fix this later? Such a pain.
  		  $this->authors[] = $child->data->author;
  		  $this->subreddits[$child->data->subreddit_id] = $child->data->subreddit;
  		  if ($child->kind == 't3') { $this->posts[] = $child->data->id; }
  		  if ($child->kind == 't1') { $this->comments[] = $child->data->id; }
  		  if (in_array($child->data->subreddit, $this->needles)) { $this->finds[$child->kind . '_' . $child->data->id] = $child->data->subreddit; }
  		  if (isset($child->data->replies) && $child->data->replies) { $this->parseShallowObj($child->data->replies, 0); }
  		}
  	}
  	if (isset($obj->data) && $obj->data->after && $top) { $this->after = $obj->data->after; }
  	$this->authors = array_unique($this->authors);
  }
  
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
    	$this->pages[$i] = new redditPage(($i ? $this->url . '.json?count=' . $count . '&after=' . $this->pages[$i-1]->after : $this->url . '.json'));
    	$this->pages[$i]->parseShallowObj();
    	$this->authors = array_merge($this->authors, $this->pages[$i]->authors);
    	foreach ($this->pages[$i]->posts as $post) {
    		$this->pages[$i]->subPages[$post] = new redditPage('www.reddit.com/comments/' . $post . '.json');
    		$this->pages[$i]->subPages[$post]->parseObj();
    		$this->authors = array_merge($this->authors, $this->pages[$i]->authors);
    	}
    	if (!$this->pages[$i]->after) { break; }
    	$count += count($this->pages[$i++]->posts);
    }
    $this->authors = array_filter(array_unique($this->authors), 'wipeBadUsers');
  }
  
}

class user {
	public $url;
	public $name;
	public $pages = array();
	public $scandepth;
	public $needles = array();
	public $finds = array();
	public $subreddits = array();
	
	function __construct($name, $scandepth, $needles) {
		$this->url = 'www.reddit.com/user/' . $name;
		$this->name = $name;
		$this->scandepth = $scandepth;
		$this->needles = $needles;
	}
	
	function scan() {
		$i = 0;
    $count = 0;
    while ($i < $this->scandepth) {
    	$this->pages[$i] = new redditPage(($i ? $this->url . '.json?count=' . $count . '&after=' . $this->pages[$i-1]->after : $this->url . '.json'), $this->needles);
    	$this->pages[$i]->parseShallowObj();
    	$this->finds = array_merge($this->finds, $this->pages[$i]->finds);
    	$this->subreddits = array_merge($this->subreddits, $this->pages[$i]->subreddits);
    	if (!$this->pages[$i]->after) { break; }
    	$count += count($this->pages[$i]->posts) + count($this->pages[$i++]->comments);
    }
    unset($this->pages);
	}
	
	
}

function wipeBadUsers($usr) {
	if (!$usr || $usr == '[deleted]') {
		return 0;
	}
	return 1;
}

function creep($scandepth, $secscandepth, $subreddits, $needles) {
	$usrs = array();
  $finds = array();
  foreach ($subreddits as $subreddit) {
  	$rp = new subReddit('www.reddit.com/r/' . $subreddit . '/', $scandepth);
    $rp->scan();
    foreach ($rp->authors as $auth) {
	    $usrs[$auth] = new user($auth, $secscandepth, $needles);
      $usrs[$auth]->scan();
      if (count($usrs[$auth]->finds)) {
  	    $finds[$auth] = $usrs[$auth]->finds;
      }
    }
    unset($rp->pages);
  }
  print_r($finds);
  print_r($usrs);
}

creep(1, 5, array('Dallas'), array('guns', 'linux', 'programming', 'Minecraft'));

?>