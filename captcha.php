<?php
require_once('inc/bootstrap.php');

class Captcha {
	private $config;
	public function __construct(array $config) {
		$this->config = $config;
	}

	private function rand_string(int $length, string $charset): string {
		$ret = "";
		while ($length--) {
			$ret .= mb_substr($charset, rand(0, mb_strlen($charset, 'utf-8')-1), 1, 'utf-8');
		}
		return $ret;
	}

	public function cleanup(string $cookie): void {
		Cache::delete("cookie_{$cookie}");
	}

	public function select_captcha(string $cookie): bool | string {
		if (!$answer = Cache::get("cookie_{$cookie}"))
			return false;
		else
			return $answer;
	}

	public function generate_captcha(): array {
		$cookie = self::rand_string(20, "abcdefghijklmnopqrstuvwxyz");
		$i = new Securimage($this->config['securimage_options']);
		$i->createCode();
		ob_start();
		$i->show();
		$rawimg = ob_get_contents();
		$b64img = 'data:image/png;base64,'.base64_encode($rawimg);
		$html = '<img src="'.$b64img.'">';
		ob_end_clean();
		$cdata = $i->getCode();
		Cache::set("cookie_{$cookie}", $cdata, $this->config['captcha']['native']['expires_in']);
		return ['cookie' => $cookie, 'html' => $html, 'rawimg' => $rawimg, 'expires_in' => $this->config['captcha']['native']['expires_in']];
	}
}

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : null;

if (is_null($mode))
	header('location: /') and exit;

switch ($mode) {
	case 'get':
		$captcha = new Captcha($config);
		$gen = $captcha->generate_captcha();

		if (isset($_GET['raw'])) {
			$_SESSION['captcha_cookie'] = $gen['cookie'];
			header('Content-Type: image/png');
			echo $gen['rawimg'];
		} else {
			unset($gen['rawimg']);
			header("Content-type: application/json");
			echo json_encode($gen);
		}
		break;
	case 'check':
		if (!isset($_GET['mode']) || !isset($_GET['cookie']) || !isset($_GET['text'])) {
			die();
		}
		$captcha = new Captcha($config);
		$check = $captcha->select_captcha($_GET['cookie']);

		header("Content-type: application/json");

		if (!$check) { // captcha expired
			echo json_encode(['success' => false]);
			break;
		} else {
			$captcha->cleanup($_GET['cookie']); // remove used captcha
		}

		if (strtolower($check) !== strtolower($_GET['text'])) {
			echo json_encode(['success' => false]);
		} else {
			echo json_encode(['success' => true]);
		}

		break;
}
