<?
$orginhttp = 'www.reddit.com/r/Dallas/';
$orginscandepth = 8;
$needle = 'guns';

class redditPage {
  public $url = '';
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
    curl_setopt($curl, CURLOPT_URL, $this->url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    if (($lastreq + 2000000) > microtime()) {
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
  }
  
}

class subReddit {
	public $scandepth;
	public $pages = array();
	
	function __construct($url, $scandepth) {
		$this->scandepth = $scandepth;
		
	}
	
	function scan() {
		$tempage = new subRedditPage($url);
		$tempage->parseNodes();
		$this->pages[] = $tempage;
		$curdepth = 1;
		$curcount = 0;
		while ($curdepth <= $scandepth) {
			$curcount += count($this->pages[$curdepth]->posts);
			$tempage = new subRedditPage($url . '.json?count=' . $curcount . '&after=' . $this->pages[$curdepth]->after);
			$tempage->parseNodes();
			$this->pages[$curdepth++] = $tempage;
		}
	}
	
}

$rp = new subRedditPage($orginhttp . '.json?count=25&after=t3_qafhl');
$rp->parseNodes();
unset($rp->obj);
print_r($rp);
?>