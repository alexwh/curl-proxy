/* used to decrypt/encrypt strings produced by the encodify/decodify functions*/
<?php
class ifier {

	protected $key = 'V01GJhIuqvxF%4TUKg45v6<4';
	public $options;

	function encodify($url) {
		$url = trim($url);
		$url = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->key), $url, MCRYPT_MODE_CBC, md5(md5($this->key)));
		$url = base64_encode($url);
		$url = rawurlencode($url);

		return $url;
	}
	function decodify($url) {
		$url = rawurldecode($url);
		$url = base64_decode($url);
		$url = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->key), $url, MCRYPT_MODE_CBC, md5(md5($this->key)));

		return $url;
	}


	// finish this
	function absolutify($input) {
		if (!$input) {
			return $input;
		}
	}
}

// Initiate class
$ifier = new ifier();

if (@$_GET['dec']) {
	$dec = $_GET['dec'];
}
if (@$_GET['enc']) {
	$enc = $_GET['enc'];
}


?>
<html>
<head>
<title>Ifier!</title>
</head>

<body>

<form method="get" action="crypt.php">
<p>Encode:</p>
<input type="text" value="" name="dec">
<br><p>Decode:</p>
<input type="text" value="" name="enc">
<br>
<input type="submit" value="Submit">
</form>

<? if (@$enc) {
	echo $ifier->decodify($enc) . "<br>";
}
if (@$dec) {
	echo $ifier->encodify($dec) . "<br>";
}?>

</body>
</html>