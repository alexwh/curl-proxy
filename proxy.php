<?php
// include the Simple HTML DOM parser - simplehtmldom.sourceforge.net
include('shd.php');

// add options to mask UA
define(@userAgent, $_SERVER['HTTP_USER_AGENT'], true);
define(@serverAddr, 'http://' . $_SERVER['SERVER_NAME'], true);
define(@proxyURL, "/sandbox/proxy.php", true);

class Proxy {

	// change this
	protected $key = 'V01GJhIuqvxF%4TUKg45v6<4'; // must be 24 bits in length
	public $options; // not yet used

	public function run() {
		session_start();
		if (!isset($_SESSION['salt'])) {
			$_SESSION['salt'] = sha1(uniqid(true));
		}

		// if there's no url get (or post) variable, redirect to home and exit
		if (@!$_GET['url'] && @!$_POST['url']) {
			header('Location: ' . serverAddr . "/sandbox/");
			exit;
		}


		// if we're still here, is it post?
		if (@$_POST['url']) {
			$targetURL = $_POST['url'];
			if (strpos($targetURL, 'http') === false) {
				$targetURL = "http://" . $targetURL;
			}

		}
		else {
			$targetURL = $_GET['url'];
		}

		// block browsing to internal urls
		if (preg_match('/^(?:127\.|192\.168\.|10\.|172\.|localhost|alexwh)/i', $targetURL)) {
			echo 'nope.avi';
			exit;
		}
		// remove any "//"'s or "/"'s at the start of the url
		$targetURL = preg_replace('/^\/\/|^\//','',$targetURL);


		// disabled due to fear of breaking encryption
		// if the URL contains a ? or other metachars, escape them
		/*if (preg_match('/["?&!@£$%^*\(\)\"\"\'\'.,~+=-`;\{\}\\"]/', $targetURL) === true) {
			$targetURL = urlencode($targetURL);
		}*/

		// if unencoded, encode and redirect
		if (strpos($targetURL, '.') !== FALSE) {
			header('Location: ' . serverAddr . proxyURL . "?url=" . $this->encodify($targetURL));
			exit;
		} else {
			$targetURL = $this->decodify($targetURL);
		}

		// if the target url doesn't start with http://, add it
		if (preg_match('/^http/', $targetURL) == 0) {
			$targetURL = "http://" . $targetURL;
		}

		// init curl and settings
		// @TODO: look into putting more (possibly user configable) settings here
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, userAgent);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/curlcook" . $_SESSION['salt'] . "-" . $_SERVER['REMOTE_ADDR']);
		curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/curlcook" . $_SESSION['salt'] . "-" . $_SERVER['REMOTE_ADDR']);

		// if we have post data and it's not from the index
		if (count ($_POST) > 0) {
			// @TODO: to be less obvious, attempt adding a hidden input to <form> instead
			if (@$_GET['p'] == 1) {
			curl_setopt($ch, CURLOPT_POST, false);
			$postdata = file_get_contents("php://input");
			$postdata = '?' . $postdata;
			$targetURL = $targetURL . $postdata;
			}
			else {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents("php://input"));
			}
		}
		curl_setopt($ch, CURLOPT_URL, $targetURL);
		ob_start();

		// exec and collect response in $data
		curl_exec($ch);
		$data = ob_get_contents();

		ob_end_clean();

		$curlinfo = curl_getinfo($ch);

		$mime = $curlinfo['content_type'];

		header("Content-Type: " . $mime);

		curl_close($ch);

		$currentURL = strtolower($curlinfo['url']);
		$currentDomain = parse_url($currentURL, PHP_URL_HOST);
		$targetDomain = parse_url($this->decodify($targetURL), PHP_URL_HOST);

		// handle redirects
		if ($targetDomain != $currentDomain) {
			$targetDomain = $currentDomain;
		}

		// @TODO: add gui to set options here
		$topbar = '<div style="position: fixed; top: 50px; z-index: 9999999;"><form method="post" action="' . serverAddr . proxyURL . '"><input type="text" name="url" value="' . $currentURL . '" maxlength="255"><br><input type="submit" value="Submit"></form></div>';

		if (!$data) {
			echo "<html>";
			echo $topbar;
			echo "<h2>Oops. We didn't know how to handle that request.</h2><br><br><br>";
			echo "The error code returned was:<br />";
			if ($curlinfo['http_code'] == 0) {
				echo "0 - cURL is upset about something, probably your URL.";
			} else {
				echo $curlinfo['http_code'];
			}
			echo "</html>";
			exit;
		}

		// Only show form and rewrite URLs if we're serving HTML
		if (strpos($mime, 'text/html') !== false) {
			// parse the html into a DOMDocument
			$dom = str_get_html($data);


			// grab all ext resources on the page and rewrite to include proxy
			foreach($dom->find('a') as $element) {
				if (strpos($element->href,'javascript:') === false) { // LEAVE javascript: ALONE
					if (preg_match('/^(http|https):\/\//', $element->href) == 0) {
						$element->href = serverAddr . proxyURL . "?url=" . $this->encodify($targetDomain . $element->href);
					} else {
						$element->href = serverAddr . proxyURL . "?url=" . $this->encodify($element->href);
					}
				}
			}

			foreach($dom->find('img') as $element) {
				// If $element->src does not contain http://
				if (strpos($element->src, 'http://') === false) {
					$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($targetDomain . $element->src);
				} else {
					if (parse_url($element->src, PHP_URL_HOST) == $currentDomain) {
						$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($element->src);
					} else {
						$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($element->src);
					}

				}
			}

			foreach($dom->find('script') as $element) {
				if (!$element->src) { // prevent adding an unneeded src attrb to script tags that don't have a src
					//nothing
				} else {
					if (preg_match('/^http[s]:\/\//',$element->src) === 0) {
						$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($targetDomain . $element->src);
					} else {
						$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($element->src);
					}
				}
			}

			foreach($dom->find('link') as $element) {
				if (preg_match('/(http|https):\/\//', $element->href) === 0) {
					$element->href = serverAddr . proxyURL . "?url=" . $this->encodify($targetDomain . $element->href);
				} else {
					$element->href = serverAddr . proxyURL . "?url=" . $this->encodify($element->href);
				}
			}

			foreach($dom->find('form') as $element) {
				if (strtolower($element->method) == "get" || isset($element->method) === false) {
					$element->method = 'post';
					$element->action = serverAddr . proxyURL . '?url='  .  $this->encodify($targetDomain . $element->action) . '&p=1';
				}
			}

			foreach($dom->find('style') as $element) {
				// add stuff
			}

			// @TODO: fix this
			foreach($dom->find('div') as $element) {
				if (isset($element->style) === true) {
					preg_match_all('/url\((http|https)(.*?)\)/', $element->style, $matches);
					foreach ($matches as $match) {
						$element->style = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($currentDomain . $match[1]), $match[1]);
					}
					// urls starting only with "/" (internal)
					preg_match_all('/url\([\/][^\/](.*?)\)/', $element->style, $matches);
					foreach ($matches as $match) {
						$element->style = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($match[1]), $match[1]);
					}
					// urls starting only with "//" (external only I think)
					preg_match_all('/url\([\/][\/](.*?)\)/', $element->style, $matches);
					foreach ($matches as $match) {
						$element->style = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($currentDomain . $match[1]), $match[1]);
					}
					// urls starting with a relative path (internal)
					preg_match_all('/url\([a-z_\-]+\/\w.*\)/', $element->style, $matches);
					foreach ($matches as $match) {
						$element->style = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($match[1]), $match[1]);
					}
				}
			}

			foreach($dom->find('embed') as $element) {
					if (strpos($element->src, 'http://') === true) {
						$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($element->src);
					}
					if (strpos($element->src, 'https://') === true) {
						$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($element->src);
					} else {
						$element->src = serverAddr . proxyURL . "?url=" . $this->encodify($targetDomain . $element->src);
					}
			}

			// begin page shown to user

			echo $topbar; // bar to set settings and navigate to urls
			echo $dom; // rewritten html

		} else {
			// define the domain the html is on
			// kinda hacky when the page is redirected, it requires a refresh on the new domain
			// @TODO: find a better way of doing this
			$htmlDomain = parse_url(str_replace(serverAddr . proxyURL . '?url=', '', $_SERVER['HTTP_REFERER']), PHP_URL_HOST);
			$GLOBALS['htmlDomain'] = $htmlDomain;

			// @TODO: fix this
			// rewrite links inside css to assets to include proxy
			if ($mime == 'text/css') {
				// handle CDNs and whatnot
//				if ($currentDomain == $htmlDomain) {
					// urls starting with http or https (external)
					$data = preg_match_all('/url\((http|https)(.*?)\)/', $data, $matches);
					foreach ($matches as $match) {
						$data = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($htmlDomain . $match[1]), $match[1]);
					}
					// urls starting only with "/" (internal)
					$data = preg_match_all('/url\([\/][^\/](.*?)\)/', $data, $matches);
					foreach ($matches as $match) {
						$data = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($match[1]), $match[1]);
					}
					// urls starting only with "//" (external only I think)
					$data = preg_match_all('/url\([\/][\/](.*?)\)/', $data, $matches);
					foreach ($matches as $match) {
						$data = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($htmlDomain . $match[1]), $match[1]);
					}
					// urls starting with a relative path (internal)
					$data = preg_match_all('/url\([a-z_\-]+\/\w.*\)/', $data, $matches);
					foreach ($matches as $match) {
						print_r($match);
						$data = str_replace($match[1], serverAddr . proxyURL . "?url=" . $this->encodify($match[1]), $match[1]);
					}
/*				} else {
					// change this behaviour to something useful
					$data = preg_replace_callback('/url\((.*?)\)/', 'Proxy::replacecss', $data);
				}*/
			}
			// the same for js
			// @TODO: parsing (and possible compilation) of entire js file to catch every url
			/*if ($mime == 'text/javascript') {

			}*/

			// @TODO: add options to not echo this data when mime is js/flash/whatever
			// echo raw data back for images and stuff
			echo $data;
		}
	}

	// @TODO: attempt to use arcfour w/stream here for speed (had problems in the past)
	function encodify($url) {
		$url = trim($url);
		$url = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->key), $url, MCRYPT_MODE_CBC, md5(md5($this->key)));
		$url = base64_encode($url);
		// prevent "="s for padding b64 (may break things, but it appears b64 auto pads when decoding)
		$url = trim($url, '=');
		$url = rawurlencode($url);

		return $url;
	}
	function decodify($url) {
		$url = rawurldecode($url);
		$url = base64_decode($url);
		$url = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->key), $url, MCRYPT_MODE_CBC, md5(md5($this->key)));

		return $url;
	}

/*	function replacecss($input) {
		// remove possible quotes in url() param
		$input = preg_replace("/[\"|']/", '', $input);
		return 'url(' . serverAddr . proxyURL . '?url=' . $this->encodify($GLOBALS['htmlDomain'] . str_replace(')', '', str_replace('url(', '', $input[1]))) . ')';
	}
	function replacecssext($input) {
		// remove possible quotes in url(); param
		$input = preg_replace("/[\"|']/", '', $input);
		return 'url(' . serverAddr . proxyURL . '?url=' . $this->encodify(str_replace('url(', '', $input[1])) . ')';
	}*/

	// @TODO: finish this
	function absolutify($input) {
		if (!$input) {
			return $input;
		}
	}
}

// Initiate our class, and 'run'
$proxy = new Proxy();
$proxy->run();

?>