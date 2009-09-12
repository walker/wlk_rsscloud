<?php
$plugin['version'] = '0.2';
$plugin['author'] = 'Walker Hamilton';
$plugin['author_uri'] = 'http://walkerhamilton.com';
$plugin['description'] = 'A down and dirty RSSCloud plugin.';

$plugin['type'] = 1;

if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = PLUGIN_LIFECYCLE_NOTIFY;

@include_once(dirname(dirname(__FILE__)).'/zem_tpl.php');

if(0) {
?>
# --- BEGIN PLUGIN HELP ---

h2. About

p. This is down and dirty RSSCloud plugin.

h2. Installation and Use

p. If you've installed and activated it, you're ready to go.

h2. Thanks

p. Some of the code is heavily based on work by Joseph Scott (http://josephscott.org/) on the Wordpress plugin.

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

register_callback('wlk_rsscloud_head', 'rss_head');
register_callback('wlk_rsscloud_notification_activation', 'pretext');

if(@txpinterface == 'admin')
{
	register_callback('wlk_rsscloud_install', 'plugin_lifecycle.wlk_rsscloud', 'installed');
	register_callback('wlk_rsscloud_delete', 'plugin_lifecycle.wlk_rsscloud', 'deleted');
	register_callback('wlk_rsscloud_language', 'admin_side', 'pagetop');
	register_callback('wlk_rsscloud_schedule', 'article', 'edit');
}

function wlk_rsscloud_schedule() {
	$rc = new WlkRssCloudClass();
	$rc->schedule_post_notifications();
}

function wlk_rsscloud_head() {
	return WlkRssCloudClass::head();
}

function wlk_rsscloud_notification_activation() {
	if(isset($_GET['rsscloud']) && $_GET['rsscloud']=='notify') {
		$rc = new WlkRssCloudClass();
		$rc->hub_process_notification_request();
		exit();
	}
}

function wlk_rsscloud_install() {
	WlkRssCloudClass::install();
}

function wlk_rsscloud_delete() {
	WlkRssCloudClass::delete();
}

function wlk_rsscloud_feeds()
{
	$rc = new WlkRssCloudClass();
	return $rc->feedDD();
}

function wlk_rsscloud_failures() {
	global $prefs;
	$vals = array(
		5 => 5,
		10 => 10,
		15 => 15,
		20 => 20
	);
	$name = 'wlk_rsscloud_max_failures';
	return selectInput($name, $vals, $prefs['wlk_rsscloud_max_failures'], '', '', $name);
}

function wlk_rsscloud_language() {
	$rc = new WlkRssCloudClass();
}

// 
class WlkRssCloudClass {
	var $vars = array(
		'notifications_instant' => true,
		'user_agent' => 'Textpattern/RSSCloud 0.2',
		'max_failures' => 5,
		'timeout' => 3
	);
	
	function __construct() {
		global $prefs, $textarray;
		
		if($prefs['language']=='en-us') {
			$textarray['wlk_rsscloud'] = 'RSSCloud';
			$textarray['rsscloud_standard'] = 'Standard feeds';
			$textarray['rsscloud_both'] = 'Both standard feeds & the custom feed';
			$textarray['rsscloud_custom_only'] = 'Custom feed only';
			$textarray['wlk_rsscloud_notifications_instant'] = 'Instant notifications? ("No" requires wlk_cron)';
			$textarray['wlk_rsscloud_max_failures'] = 'Max # of failures';
			$textarray['wlk_rsscloud_feeds'] = 'Set type of feeds in use';
			$textarray['wlk_rsscloud_timeout'] = 'Timeout';
			$textarray['wlk_rsscloud_user_agent'] = 'User-agent';
			$textarray['wlk_rsscloud_custom_url'] = 'Custom feed URL';
		}
		
		if(isset($prefs['wlk_rsscloud_notifications_instant']) && !empty($prefs['wlk_rsscloud_notifications_instant'])) { $this->vars['notifications_instant'] = (bool)$prefs['wlk_rsscloud_notifications_instant']; }
		if(isset($prefs['wlk_rsscloud_max_failures']) && !empty($prefs['wlk_rsscloud_max_failures'])) { $this->vars['max_failures'] = (int)$prefs['wlk_rsscloud_max_failures']; }
		if(isset($prefs['wlk_rsscloud_timeout']) && !empty($prefs['wlk_rsscloud_timeout'])) { $this->vars['timeout'] = (int)$prefs['wlk_rsscloud_timeout']; }
		if(isset($prefs['wlk_rsscloud_user_agent']) && !empty($prefs['wlk_rsscloud_user_agent'])) { $this->vars['user_agent'] = $prefs['wlk_rsscloud_user_agent']; }
		if(isset($prefs['wlk_rsscloud_custom_url']) && !empty($prefs['wlk_rsscloud_custom_url'])) { $this->vars['custom_url'] = $prefs['wlk_rsscloud_custom_url']; }
	}
	
	function WlkRssCloudClass() {
		$this->__construct();
	}
	
	function feedDD() {
		global $prefs;
		$vals = array(
			'standard' => gTxt('rsscloud_standard'),
			'both' => gTxt('rsscloud_both'),
			'custom_only' => gTxt('rsscloud_custom_only'),
		);
		$name = 'wlk_rsscloud_feeds';
		return selectInput($name, $vals, $prefs['wlk_rsscloud_feeds'], '', '', $name);
	}
	
	/* Needed HTTP Stuff - Should really create a #2 library plugin for this*/
	function remote_post($url, $args = array()) {
		$defaults = array(
			'method' => 'post',
			'body' => array(),
			'options' => array('path'=>'/', 'port' => 80)
		);
		
		if(isset($args['options'])) {
			$final_options = array_merge($defaults['options'], $args['options']);
		} else {
			$final_options = $defaults['options'];
		}
		$final_args = array_merge($defaults, $args);
		$final_args['options'] = $final_options;
		$args = $final_args;
		
		$result = array();
		
		// TODO: Should really make fopen, fsockopen, & exthttp work...
		if(function_exists('curl_init') && function_exists('curl_exec')) {
			$curl = curl_init(str_replace('//', '/', $url.$args['options']['path']));
			curl_setopt($curl, CURLOPT_USERAGENT, $this->vars['user_agent']);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $final_args['body']);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_TIMEOUT, $this->vars['timeout']);
			$result['response'] = curl_exec($curl);
			$result['code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
		} // else if(function_exists('fsockopen')) {
		// 	return $this->_fsock_post('http', $final_args['url'], $final_args['options']['port'], $final_args['options']['path'], $final_args['body']);
		// } else if(function_exists('http_request')) {
		// 	return http_request($final_args['method'}, $final_args['url'], $final_args['body'], $final_args['options']);
		// }
	}
	
	// function _fsock_post($type,$host,$port='80',$path='/',$data='') { 
	// 	$_err = 'lib sockets::'.__FUNCTION__.'(): '; 
	// 	switch($type) { case 'http': $type = ''; case 'ssl': continue; default: die($_err.'bad $type'); } if(!ctype_digit($port)) die($_err.'bad port'); 
	// 	if(!empty($data)) foreach($data AS $k => $v) $str .= urlencode($k).'='.urlencode($v).'&'; $str = substr($str,0,-1); 
	// 	
	// 	$fp = fsockopen($host,$port,$errno,$errstr,$timeout=30);
	// 	if(!$fp) die($_err.$errstr.$errno); else {
	// 		fwrite($fp, "POST $path HTTP/1.1\r\n");
	// 		fwrite($fp, "Host: $host\r\n");
	// 		fwrite($fp, "User-Agent: ".$this->vars['user_agent']."\r\n");
	// 		fwrite($fp, "Content-type: application/x-www-form-urlencoded\r\n");
	// 		fwrite($fp, "Content-length: ".strlen($str)."\r\n");
	// 		fwrite($fp, "Connection: close\r\n\r\n");
	// 		fwrite($fp, $str."\r\n\r\n");
	// 		
	// 		while(!feof($fp)) $d .= fgets($fp,4096);
	// 		fclose($fp);
	// 	}
	// 	return $d;
	// }
	
	function head() {
		$cloud = parse_url(hu.'?rsscloud=notify');
		$cloud['port'] = (int) $cloud['port'];
		
		if(empty( $cloud['port'])) { $cloud['port'] = 80; }
		
		$cloud['path'] .= "?{$cloud['query']}";
		$cloud['host'] = strtolower( $cloud['host'] );
		
		return "<cloud domain='{$cloud['host']}' port='{$cloud['port']}' path='{$cloud['path']}' registerProcedure='' protocol='http-post' />"."\n";
	}
	
	function notify_result($success, $msg) {
		header('Content-Type: text/xml');
		echo "<?xml version='1.0'"."?".">"."\n";
		echo "<notifyResult success='{$success}' msg='{$msg}' />\n";
		exit;
	}

	function schedule_post_notifications() {
		if($this->vars['notifications_instant'] && function_exists('wlk_cron_single_event')) {
			// TODO: create wlk_cron Plugin
			wlk_cron_single_event(time(), 'send_post_notifications_action');
		} else {
			$this->send_post_notifications();
		}
	}

	function send_post_notifications() {
		global $prefs;
		$send_ud = false;
		if(!empty($_POST) && isset($_POST['ID'])) {
			if(is_numeric($_POST['ID'])) {
				$a = safe_row('Status', 'textpattern', 'ID='.intval($id));
				if ($a) {
					if($uExpires and time() > $uExpires and !$prefs['publish_expired_articles']) {
						$send_ud = false;
						return;
					}
					if(($a['Status']!=4 || $a['Status']!=5) && ($_POST['Status']==4 || $_POST['Status']==5)) {
						// status changed to published
						$send_ud = true;
					} else {
						// status didn't change to published
						$send_ud = false;
						return;
					}
				} else {
					$send_ud = false;
				}
			} else if((!is_numeric($_POST['ID']) || empty($_POST['ID'])) && ($_POST['Status']==4 || $_POST['Status']==5)) {
				if(!$this->expired($_POST) && $this->published($_POST)) {
					$send_ud = true;
				} else if(!$this->expired($_POST) && !$this->published($_POST)) {
					// TODO: Set cron
					$send_ud = false;
					return;
				}
			} else {
				// error!
				$send_ud = false;
				return;
			}
		}
		
		if($send_ud) {
			$urls = array();
			
			if($prefs['wlk_rsscloud_feeds']=='custom_only' || $prefs['wlk_rsscloud_feeds']=='both') {
				$urls[] = $prefs['custom_url'];
			}
		
			if($prefs['wlk_rsscloud_feeds']=='both' || $prefs['wlk_rsscloud_feeds']=='standard') {
				// get the section, category, & "All"
				$frs = safe_column("name", "txp_section", "in_rss != '1'");
				if(isset($_POST['Section']) && in_array($_POST['Section'], $frs)) {
					// can't send that section
				} else {
					// construct the feed URLs
					// add to the URLs array
					$urls[] = hu.'rss/';
				
					if(isset($_POST['Section']) && !empty($_POST['Section'])) {
						$urls[] = hu.'rss/?section='.$_POST['Section'];
					}
				
					if(isset($_POST['Category1']) && !empty($_POST['Category1'])) {
						$urls[] = hu.'rss/?category='.$_POST['Category1'];
						if(isset($_POST['Section']) && !empty($_POST['Section'])) {
							$urls[] = hu.'rss/?section='.$_POST['Section'].'&category='.$_POST['Category1'];
						}
					}
				
					if(isset($_POST['Category2']) && !empty($_POST['Category2'])) {
						$urls[] = hu.'rss/?category='.$_POST['Category2'];
						if(isset($_POST['Section']) && !empty($_POST['Section'])) {
							$urls[] = hu.'rss/?section='.$_POST['Section'].'&category='.$_POST['Category2'];
						}
					}
				}
			}
			
			if(!empty($urls)) {
				$feed_condition_arr = array();
				foreach($urls as $url) {
					$feed_condition_arr[] = 'feed_url="'.addslashes($url).'"';
				}
				$feed_conditions = implode(' OR ', $feed_condition_arr);
				
				$notify = safe_rows('*', 'txp_rsscloud_notifications', '('.$feed_conditions.') AND status="active"');
				
				foreach($notify as $n)
				{
					if($n['status'] == 'active')
					{
						if ($n['protocol'] == 'http-post')
						{
							$url = parse_url($n['notify_url']);
							$port = 80;
							if(!empty($url['port'])) { $port = $url['port']; }
							
							$result = $this->remote_post($n['notify_url'], array('method' => 'POST', 'port' => $port, 'body' => array('url' => $n['feed_url'])));
							
							$need_update = false;
							if($result['code']!=200)
							{
								$n['fail_count'] = $n['fail_count']+1;
								safe_update('txp_rsscloud_notifications', 'fail_count="'.$n['fail_count'].'"', 'id="'.$n['id'].'"');
							}
							
							if($n['fail_count'] > $this->vars['max_failures']) {
								safe_update('txp_rsscloud_notifications', 'status="suspended"', 'id="'.$n['id'].'"');
							}
						}
					}
				} // foreach
			}
		}
	}

	function published($post) {
		$when_ts = time();
		
		if(isset($post['reset_time'])) {
			return true;
		} else {
			if (!is_numeric($post['year']) || !is_numeric($post['month']) || !is_numeric($post['day']) || !is_numeric($post['hour'])  || !is_numeric($post['minute']) || !is_numeric($post['second']) ) {
				return false;
			}
			$ts = strtotime($post['year'].'-'.$post['month'].'-'.$post['day'].' '.$post['hour'].':'.$post['minute'].':'.$post['second']);
			if ($ts === false || $ts === -1) {
				return false;
			}
			
			$when = $when_ts = $ts - tz_offset($ts);
			
			if($when<=time())
				return true;
			else
				return false;
		}
	}

	function expired($post) {
		if(isset($post['exp_year']) && !empty($post['exp_year'])) {
			if(empty($post['exp_month'])) $post['exp_month']=1;
			if(empty($post['exp_day'])) $post['exp_day']=1;
			if(empty($post['exp_hour'])) $post['exp_hour']=0;
			if(empty($post['exp_minute'])) $post['exp_minute']=0;
			if(empty($post['exp_second'])) $post['exp_second']=0;
			
			$ts = strtotime($post['exp_year'].'-'.$post['exp_month'].'-'.$post['exp_day'].' '.$post['exp_hour'].':'.$post['exp_minute'].':'.$post['exp_second']);
			$expires = $ts - tz_offset($ts);
			
			if($expires<=time())
				return true;
			else
				return false;
		} else {
			return false;
		}
	}

	function hub_process_notification_request() {
		global $prefs;
		
		// Must provide at least one URL to get notifications about
		if(isset($_POST['url1']) && empty($_POST['url1']))
			$this->notify_result('false', 'No feed for url1.');
		
		// Only support http-post
		$protocol = 'http-post';
		if(isset($_POST['protocol']) && !empty($_POST['protocol']) && strtolower($_POST['protocol'])!=='http-post')
			$this->notify_result('false', 'Only http-post notifications are supported at this time.');
		
		// Assume port 80
		$port = 80;
		if(isset($_POST['port']) && !empty($_POST['port'])) { $port = (int)$_POST['port']; }
		
		// Path is required
		if((isset($_POST['path']) && empty($_POST['path'])) || !isset($_POST['path']))
		{
			$this->notify_result('false', 'No path provided.');
		} else if(isset($_POST['path'])) {
			$path = $_POST['path'];
		}
		
		// Process each URL request: url1, url2, url3 ... urlN
		$i = 1;
		while(isset($_POST['url'.$i]))
		{
			$feed_url = $_POST['url'.$i];
			if(!preg_match('|url\d+|', $feed_url))
			{
				
			} else if(!in_array($this->vars['custom_rss'], $feed_url) && $prefs['rsscloud_feeds']=='custom_only') {
				
			} else if(!in_array($this->vars['custom_rss'], $feed_url) && $prefs['rsscloud_feeds']=='both' && strpos($feed_url, 'rss')===false) {
				
			} else if($prefs['rsscloud_feeds']=='standard' && strpos($feed_url, 'rss')===false) { // Only allow requests for the RSS feed
				
			} else {
				// $rss2_url = get_bloginfo('rss2_url');
				$notify_url = $_SERVER['REMOTE_ADDR'].':'.$port.$path;
				$notify = safe_row('*', 'txp_rsscloud_notifications', 'feed_url="'.$feed_url.'" AND notify_url="'.$notify_url.'" AND failure<"'.addslashes($this->vars['max_failures']).'" AND status="active"');
				
				if(empty($notify)) {
					// Attempt a notification to see if it will work
					$result = $this->remote_post($notify_url, array('method'=>'POST', 'timeout'=>$this->vars['timeout'], 'user-agent'=>$this->vars['user_agent'], 'port'=>$port, 'body'=>array('url' => $_POST['url'.$i])));
					if(isset($result->errors['http_request_failed'][0]))
						$this->notify_result('false', 'Error testing notification URL : '.$result->errors['http_request_failed'][0]);
					if($result['response']['code'] != 200)
						$this->notify_result('false', 'Error testing notification URL.');
					
					// Passed all the tests, add this to the list of notifications for
					$status = 'active';
					$failure_count = 0;
					safe_insert('txp_rsscloud_notifications', '(feed_url, notify_url, protocol, status, fail_count) VALUES ("'.addslashes($feed_url).'", "'.addslashes($notify_url).'", "'.addslashes($protocol).'", "'.addslashes($status).'", "'.addslashes($failure_count).'")');
					$this->notify_result('true', 'Registration successful.');
				} else {
					// already registered for pings
					$this->notify_result('true', 'Registration for that feed/notify URL already exists.');
				}
			}
			$i++;
		}
	} // function hub_notify
	
	
	/* Install Goes Here */
	function install() {
		safe_query('DELETE FROM '.safe_pfx('txp_prefs').' WHERE name LIKE "wlk_rsscloud_%"');
		safe_insert('txp_prefs',"prefs_id = '1',name = 'wlk_rsscloud_notifications_instant',val = '1',type = '1',event = 'wlk_rsscloud',html = 'yesnoradio',position = '10',user_name = ''");
		safe_insert('txp_prefs',"prefs_id = '1',name = 'wlk_rsscloud_max_failures',val = '0',type = '1',event = 'wlk_rsscloud',html = 'wlk_rsscloud_failures',position = '20',user_name = ''");
		safe_insert('txp_prefs',"prefs_id = '1',name = 'wlk_rsscloud_timeout',val = '3',type = '1',event = 'wlk_rsscloud',html = 'text_input',position = '30',user_name = ''");
		safe_insert('txp_prefs',"prefs_id = '1',name = 'wlk_rsscloud_user_agent',val = 'Textpattern/RSS Cloud 0.2',type = '1',event = 'wlk_rsscloud',html = 'text_input',position = '40',user_name = ''");
		safe_insert('txp_prefs',"prefs_id = '1',name = 'wlk_rsscloud_feeds',val = 'standard',type = '1',event = 'wlk_rsscloud',html = 'wlk_rsscloud_feeds',position = '50',user_name = ''");
		safe_insert('txp_prefs',"prefs_id = '1',name = 'wlk_rsscloud_custom_url',val = '',type = '1',event = 'wlk_rsscloud',html = 'text_input',position = '60',user_name = ''");
		safe_query("CREATE TABLE IF NOT EXISTS `".safe_pfx('txp_rsscloud_notifications')."` (
			`id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`feed_url` VARCHAR( 255 ) NOT NULL ,
			`notify_url` VARCHAR( 255 ) NOT NULL ,
			`protocol` VARCHAR( 20 ) NOT NULL DEFAULT  'http-post',
			`status` VARCHAR( 20 ) NOT NULL DEFAULT  'active',
			`fail_count` TINYINT( 4 ) NOT NULL DEFAULT  '0',
			INDEX (  `feed_url` )
		) ENGINE = MYISAM");
	}

	function delete() {
		safe_delete('txp_prefs',"name LIKE 'wlk_rsscloud_%'");
		safe_query('DROP TABLE IF EXISTS `'.safe_pfx('txp_rsscloud_notifications').'`');
	}
}

# --- END PLUGIN CODE ---
?>