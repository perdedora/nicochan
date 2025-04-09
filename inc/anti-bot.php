<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

class AntiBot
{
	public string $salt;
	public array $inputs = [];
	private int $index = 0;
	private array $config;

	public function __construct(array $config, array $salt = [])
	{
		$this->config = $config;
		$this->salt = !empty($salt) ? implode(':', $salt) : '';

		shuffle($this->config['spam']['hidden_input_names']);
		$inputCount = mt_rand($this->config['spam']['hidden_inputs_min'], $this->config['spam']['hidden_inputs_max']);
		$this->generateInputs($inputCount);
	}

	private function generateInputs(int $inputCount): void
	{
		$hiddenInputIndex = 0;

		for ($i = 0; $i < $inputCount; $i++) {
			$name = ($hiddenInputIndex === false || mt_rand(0, 2) === 0)
				? self::randomString(mt_rand(10, 40), false, false, $this->config['spam']['unicode'])
				: $this->config['spam']['hidden_input_names'][$hiddenInputIndex++];

			if ($hiddenInputIndex >= count($this->config['spam']['hidden_input_names'])) {
				$hiddenInputIndex = false;
			}

			$this->inputs[$name] = $this->generateInputValue();
		}
	}

	private function generateInputValue(): string
	{
		return match (mt_rand(0, 4)) {
			0, 1 => '',
			2, 3 => (string)mt_rand(0, 100000),
			default => self::randomString(mt_rand(5, 100), true, true, $this->config['spam']['unicode'])
		};
	}

	public static function randomString(int $length, bool $uppercase = false, bool $specialChars = false, bool $unicodeChars = false): string
	{
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$chars .= $uppercase ? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' : '';
		$chars .= $specialChars ? ' ~!@#$%^&*()_+,./;\'[]\\{}|:<>?=-` ' : '';

		if ($unicodeChars) {
			$chars .= self::generateUnicodeChars();
		}

		$charsArray = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);
		shuffle($charsArray);

		return implode('', array_slice($charsArray, 0, $length));
	}

	private static function generateUnicodeChars(): string
	{
		$unicodeChars = '';
		$len = strlen($unicodeChars) / 10;
		for ($n = 0; $n < $len; $n++) {
			$unicodeChars .= mb_convert_encoding('&#' . mt_rand(0x2600, 0x26FF) . ';', 'UTF-8', 'HTML-ENTITIES');
		}
		return $unicodeChars;
	}

	public function html(?int $count = null): string
	{
		$elements = [
			'<input type="hidden" name="%name%" value="%value%">',
			'<input type="hidden" value="%value%" name="%name%">',
			'<input name="%name%" value="%value%" type="hidden">',
			'<input value="%value%" name="%name%" type="hidden">',
			'<input style="display:none" type="text" name="%name%" value="%value%">',
			'<textarea style="display:none" name="%name%">%value%</textarea>'
		];

		$count = $count ?? mt_rand(1, (int) abs(count($this->inputs) / 15) + 1);
		$inputs = array_slice($this->inputs, $this->index, $count === true ? null : $count);
		$this->index += count($inputs);

		$html = '';
		foreach ($inputs as $name => $value) {
			$element = $this->getRandomElement($elements);
			$html .= $this->renderElement($element, $name, $value);
		}

		return $html;
	}

	private function getRandomElement(array $elements): string
	{
		$element = null;
		while (!$element) {
			$element = str_replace(' ', self::space(), $elements[array_rand($elements)]);
			if (mt_rand(0, 5) === 0) {
				$element = str_replace('>', self::space() . '>', $element);
			}
			if (strpos($element, 'textarea') !== false && trim($element) === '') {
				$element = null;  // Avoid empty <textarea> issues in mobile browsers
			}
		}
		return $element;
	}

	private function renderElement(string $element, string $name, string $value): string
	{
		$element = str_replace('%name%', utf8tohtml($name), $element);
		$value = mt_rand(0, 2) === 0 ? self::makeConfusing($value) : utf8tohtml($value);
		if (strpos($element, 'textarea') === false) {
			$value = str_replace('"', '&quot;', $value);
		}
		return str_replace('%value%', $value, $element);
	}

	public static function space(): string
	{
		return mt_rand(0, 3) !== 0 ? ' ' : str_repeat(' ', mt_rand(1, 3));
	}

	public static function makeConfusing(string $string): string
	{
		$chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($chars as &$char) {
			$char = mt_rand(0, 3) !== 0
				? utf8tohtml($char)
				: mb_encode_numericentity($char, [0, 0xFFFF, 0, 0xFFFF], 'UTF-8');
		}

		return implode('', $chars);
	}

	public function reset(): void
	{
		$this->index = 0;
	}

	public function hash(): string
	{
		ksort($this->inputs);  // Sort inputs alphabetically

		$hash = '';
		foreach ($this->inputs as $name => $value) {
			$hash .= "$name=$value";
		}
		$hash .= $this->config['cookies']['salt'];

		return sha1($hash . $this->salt);
	}
}
