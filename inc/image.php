<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

class Image {
	public string $src;
	public string|false $format;
	public ImageBase $image;
	public object $size;

	public function __construct($src, $format = false, $size = false) {
		global $config;

		$this->src = $src;
		$this->format = $format;

		switch ($config['thumb_method']) {
			case 'imagick':
				$classname = 'ImageImagick';
				break;
			case 'convert':
			case 'convert+gifsicle':
			case 'gm':
			case 'gm+gifsicle':
				$classname = 'ImageConvert';
				break;
			default:
				$classname = 'Image' . strtoupper($this->format);
				if (!class_exists($classname)) {
					error(_('Unsupported file format: ') . $this->format);
				}
				break;
		}

		$this->image = new $classname($this, $size);

		if (!$this->image->valid()) {
			$this->delete();
			error($config['error']['invalidimg']);
		}

		$this->size = (object)array('width' => $this->image->_width(), 'height' => $this->image->_height());
		if ($this->size->width < 1 || $this->size->height < 1) {
			$this->delete();
			error($config['error']['invalidimg']);
		}
	}

	public function resize($extension, $max_width, $max_height) {
		global $config;

		switch ($config['thumb_method']) {
			case 'imagick':
				$classname = 'ImageImagick';
				break;
			case 'convert':
				$classname = 'ImageConvert';
				break;
			case 'convert+gifsicle':
				$classname = 'ImageConvert';
				break;
			case 'gm':
				$classname = 'ImageConvert';
				break;
			case 'gm+gifsicle':
				$classname = 'ImageConvert';
				break;
			default:
				$classname = 'Image' . strtoupper($extension);
				if (!class_exists($classname)) {
					error(_('Unsupported file format: ') . $extension);
				}
	   			break;
		}


		$thumb = new $classname(false);
		$thumb->src = $this->src;
		$thumb->format = $this->format;
		$thumb->original_width = $this->size->width;
		$thumb->original_height = $this->size->height;

		$x_ratio = $max_width / $this->size->width;
		$y_ratio = $max_height / $this->size->height;

		if (($this->size->width <= $max_width) && ($this->size->height <= $max_height)) {
			$width = $this->size->width;
			$height = $this->size->height;
		} elseif (($x_ratio * $this->size->height) < $max_height) {
			$height = ceil($x_ratio * $this->size->height);
			$width = $max_width;
		} else {
			$width = ceil($y_ratio * $this->size->width);
			$height = $max_height;
		}

		$thumb->_resize($this->image->image, $width, $height);

		return $thumb;
	}

	public function to($dst) {
		$this->image->to($dst);
	}

	public function delete() {
		file_unlink($this->src);
	}
	public function destroy() {
		$this->image->_destroy();
	}
}

class ImageGD {
	public int $width = 0;
	public int $height = 0;
	public int $original_width = 0;
	public int $original_height = 0;
	public mixed $original = null;
	public mixed $image = null;

	public function GD_create() {
		$this->image = imagecreatetruecolor($this->width, $this->height);
	}
	public function GD_copyresampled() {
		imagecopyresampled($this->image, $this->original, 0, 0, 0, 0, $this->width, $this->height, $this->original_width, $this->original_height);
	}
	public function GD_resize() {
		$this->GD_create();
		$this->GD_copyresampled();
	}
}

class ImageBase extends ImageGD {
	public string $src = '';
	public string|false $format = false;

	public function valid() {
		return (bool)$this->image;
	}

	public function __construct($img, $size = false) {
		if (method_exists($this, 'init'))
			$this->init();

		if ($size && $size[0] > 0 && $size[1] > 0) {
			$this->width = $size[0];
			$this->height = $size[1];
		}

		if ($img !== false) {
			$this->src = $img->src;
			$this->from();
		}
	}

	public function _width() {
		if (method_exists($this, 'width'))
			return $this->width();
		// use default GD functions
		return imagesx($this->image);
	}
	public function _height() {
		if (method_exists($this, 'height'))
			return $this->height();
		// use default GD functions
		return imagesy($this->image);
	}
	public function _destroy() {
		if (method_exists($this, 'destroy'))
			return $this->destroy();
		// use default GD functions
		return imagedestroy($this->image);
	}
	public function _resize($original, $width, $height) {
		$this->original = &$original;
		$this->width = $width;
		$this->height = $height;

		if (method_exists($this, 'resize'))
			$this->resize();
		else
			// use default GD functions
			$this->GD_resize();
	}
}

class ImageImagick extends ImageBase {
	public function init() {
		$this->image = new Imagick();
		$this->image->setBackgroundColor(new ImagickPixel('transparent'));
	}
	public function from() {
		try {
			$this->image->readImage($this->src);
		} catch(ImagickException $e) {
			// invalid image
			$this->image = false;
		}
	}
	public function to($src) {
		global $config;
		if ($config['strip_exif']) {
			$this->image->stripImage();
		}
		if (preg_match('/\.gif$/i', $src))
			$this->image->writeImages($src, true);
		else
			$this->image->writeImage($src);
	}
	public function width() {
		return $this->image->getImageWidth();
	}
	public function height() {
		return $this->image->getImageHeight();
	}
	public function destroy() {
		return $this->image->destroy();
	}
	public function resize() {
		global $config;

		if ($this->format == 'gif' && in_array($config['thumb_ext'], ['gif', ''])) {
			$this->image = new Imagick();
			$this->image->setFormat('gif');

			$keep_frames = array();
			for ($i = 0; $i < $this->original->getNumberImages(); $i += floor($this->original->getNumberImages() / $config['thumb_keep_animation_frames']))
				$keep_frames[] = $i;

			$i = 0;
			$delay = 0;
			foreach ($this->original as $frame) {
				$delay += $frame->getImageDelay();

				if (in_array($i, $keep_frames)) {
					$frame->sampleImage($this->width, $this->height);
					$frame->setImagePage($this->width, $this->height, 0, 0);
					$frame->setImageDelay($delay);
					$delay = 0;

					$this->image->addImage($frame->getImage());
				}
				$i++;
			}
		} else {
			$this->image = clone $this->original;
			$this->image->scaleImage($this->width, $this->height, false);
		}
	}
}


class ImageConvert extends ImageBase {
	public ?string $temp = null;
	public bool $gm = false;
	public bool $gifsicle = false;

	public function init() {
		global $config;

		$this->gm = in_array($config['thumb_method'], ['gm', 'gm+gifsicle']);
		$this->gifsicle = in_array($config['thumb_method'], ['convert+gifsicle', 'gm+gifsicle']);

		$this->temp = false;
	}
	public function get_size($src, $try_gd_first = true) {
		if ($try_gd_first && ($size = @getimagesize($src))) {
			return $size;
		}
		$size = shell_exec_error(($this->gm ? 'gm ' : '') . 'identify -format "%w %h" ' . escapeshellarg($src . '[0]'));
		if (preg_match('/^(\d+) (\d+)$/', $size, $m))
			return array($m[1], $m[2]);
		return false;
	}
	public function from() {
		if ($this->width > 0 && $this->height > 0) {
			$this->image = true;
			return;
		}
		$size = $this->get_size($this->src, false);
		if ($size) {
			$this->width = $size[0];
			$this->height = $size[1];

			$this->image = true;
		} else {
			// mark as invalid
			$this->image = false;
		}
	}
	public function to($src) {
		global $config;

		if (!$this->temp) {
			if ($config['strip_exif']) {
				if($error = shell_exec_error(($this->gm ? 'gm ' : '') . 'convert ' .
						escapeshellarg($this->src) . ' -auto-orient -strip ' . escapeshellarg($src))) {
					$this->destroy();
					error(_('Failed to redraw image!'), null, $error);
				}
			} else {
				if($error = shell_exec_error(($this->gm ? 'gm ' : '') . 'convert ' .
						escapeshellarg($this->src) . ' -auto-orient ' . escapeshellarg($src))) {
					$this->destroy();
					error(_('Failed to redraw image!'), null, $error);
				}
			}
		} else {
			rename($this->temp, $src);
			chmod($src, 0664);
		}
	}
	public function width() {
		return $this->width;
	}
	public function height() {
		return $this->height;
	}
	public function destroy() {
		if(file_exists($this->temp))
			unlink($this->temp);
		$this->temp = false;
	}
	public function resize()
	{
		global $config;

		if ($this->temp) {
			// remove old
			$this->destroy();
		}

		$this->temp = tempnam($config['tmp'], 'convert');

		$frames = (int)$config['thumb_keep_animation_frames'];

		if ($this->format == 'gif' && $this->gifsicle 
			&& in_array($config['thumb_ext'], ['', 'gif']) 
			&& $config['thumb_keep_animation_frames'] > 1
		) {
			$this->resizeAnimatedGif($frames);
		} elseif ($this->format == 'gif' && !$this->gifsicle 
			&& in_array($config['thumb_ext'], ['', 'webp']) 
			&& $config['thumb_keep_animation_frames'] > 1
		) {
			$this->convertGifToWebp($frames);
		} elseif ($this->format == 'webp' && $this->isAnimatedWebp()) {
			$this->resizeAnimatedWebp($frames);
		} else {
			$this->resizeStaticImage($config);
		}

		if ($size = $this->get_size($this->temp)) {
			$this->width = $size[0];
			$this->height = $size[1];
		}
	}

	private function resizeAnimatedGif(int $frames)
	{
		$output = [];
		$return_var = 0;

		$command = sprintf(
			'gifsicle -w --unoptimize -O2 --resize %dx%d < %s "#0-%d" -o %s',
			$this->width,
			$this->height,
			escapeshellarg($this->src),
			$frames,
			escapeshellarg($this->temp)
		);
		exec($command, $output, $return_var);

		$this->checkAndHandleError($return_var, $this->temp, $output, _('Failed to resize GIF!'));
	}

	private function convertGifToWebp(int $frames)
	{
		$output = [];
		$return_var = 0;

		$tempFile = $this->temp . '.webp';

		$command = sprintf(
			'convert %s[0-%d] -coalesce -quality 80 -resize %dx%d -size %dx%d -thumbnail %dx%d -loop 0 -auto-orient ' .
			'-define webp:lossless=false -layers Optimize %s',
			escapeshellarg($this->src),
			$frames - 1,
			$this->width, // resize
			$this->height, // resize
			$this->width, // size
			$this->height, // size
			$this->width, // thumbnail
			$this->height, // thumbnail
			escapeshellarg($tempFile)
		);
		exec($command, $output, $return_var);

		$this->checkAndHandleError($return_var, $tempFile, $output, _('Failed to convert GIF to WebP!'));

		rename($tempFile, $this->temp);
	}

	private function resizeAnimatedWebp(int $frames)
	{
		$output = [];
		$return_var = 0;

		$tempFile = $this->temp . '.webp';

		// gm is shitty here
		$command = sprintf(
			'convert %s[0-%d] -coalesce -quality 80 -resize %dx%d -size %dx%d -thumbnail %dx%d -loop 0 -auto-orient ' .
			'-layers Optimize %s',
			escapeshellarg($this->src),
			$frames - 1,
			$this->width, // resize
			$this->height, // resize
			$this->width, // size
			$this->height, // size
			$this->width, // thumbnail
			$this->height, // thumbnail
			escapeshellarg($tempFile)
		);

		exec($command . ' 2>&1', $output, $return_var);

		$this->checkAndHandleError($return_var, $tempFile, $output, _('Failed to resize animated WebP!'));

		rename($tempFile, $this->temp);
	}

	private function resizeStaticImage(array $config)
	{
		$convert_args = $this->getConvertArgs($config); 

		$command = sprintf(
			'%sconvert %s',
			$this->gm ? 'gm ' : '',
			sprintf(
				$convert_args,
				$this->width,
				$this->height,
				escapeshellarg($this->src . '[0]'),
				$this->width,
				$this->height,
				escapeshellarg($this->temp)
			)
		);

		$error = shell_exec_error($command);

		if ($error || !file_exists($this->temp)) {
			if ($this->shouldTriggerError($error)) {
				$this->destroy();
				error(
					_('Failed to resize image!') . " " . 
					_('Details: ') . nl2br(htmlspecialchars($error)),
					null,
					['convert_error' => $error]
				);
			}
			if (!file_exists($this->temp)) {
				$this->destroy();
				error(_('Failed to resize image!'), null, $error);
			}
		}
	}

	private function getConvertArgs(array $config)
	{
		if ($config['convert_manual_orient']) {
			return ($this->format == 'jpg' || $this->format == 'jpeg') 
				? str_replace(
					'-auto-orient',
					ImageConvert::jpeg_exif_orientation($this->src),
					$config['convert_args']
				) 
				: str_replace('-auto-orient', '', $config['convert_args']);
		}
		return $config['convert_args'];
	}

	private function shouldTriggerError(string $error)
	{
		$ignorableErrors = [
			"known incorrect sRGB profile",
			"iCCP: Not recognizing known sRGB profile that has been edited",
			"cHRM chunk does not match sRGB"
		];

		foreach ($ignorableErrors as $ignorableError) {
			if (strpos($error, $ignorableError) !== false) {
				return false;
			}
		}

		return true;
	}

	private function isAnimatedWebp()
	{
		$info = shell_exec(sprintf(
			'identify %s',
			escapeshellarg($this->src)
		));
		return preg_match('/\[\d+\] \w/', $info); // check if the output contains multiple frames

	}

	private function checkAndHandleError(int $return_var, string $tempFile, array $output, string $errorMessage)
	{
		if ($return_var !== 0 || !file_exists($tempFile)) {
			$this->destroy();
			$error_message = implode("\n", $output);
			error($errorMessage, null, $error_message);
		}
	}
	// For when -auto-orient doesn't exist (older versions)
	public static function jpeg_exif_orientation($src, $exif = false) {
		if (!$exif) {
			$exif = @exif_read_data($src);
			if (!isset($exif['Orientation']))
				return false;
		}
		switch($exif['Orientation']) {
			case 1:
				// Normal
				return false;
			case 2:
				// 888888
				//     88
				//   8888
				//     88
				//     88

				return '-flop';
			case 3:

				//     88
				//     88
				//   8888
				//     88
				// 888888

				return '-flip -flop';
			case 4:
				// 88
				// 88
				// 8888
				// 88
				// 888888

				return '-flip';
			case 5:
				// 8888888888
				// 88  88
				// 88

				return '-rotate 90 -flop';
			case 6:
				// 88
				// 88  88
				// 8888888888

				return '-rotate 90';
			case 7:
				//         88
				//     88  88
				// 8888888888

				return '-rotate "-90" -flop';
			case 8:
				// 8888888888
				//     88  88
				//         88

				return '-rotate "-90"';
			default:
				return '';
		}
	}
}

class ImagePNG extends ImageBase {
	public function from() {
		$this->image = @imagecreatefrompng($this->src);
	}
	public function to($src) {
		global $config;
		imagepng($this->image, $src);
	}
	public function resize() {
		$this->GD_create();
		imagecolortransparent($this->image, imagecolorallocatealpha($this->image, 0, 0, 0, 0));
		imagesavealpha($this->image, true);
		imagealphablending($this->image, false);
		$this->GD_copyresampled();
	}
}

class ImageGIF extends ImageBase {
	public function from() {
		$this->image = @imagecreatefromgif($this->src);
	}
	public function to($src) {
		imagegif ($this->image, $src);
	}
	public function resize() {
		$this->GD_create();
		imagecolortransparent($this->image, imagecolorallocatealpha($this->image, 0, 0, 0, 0));
		imagesavealpha($this->image, true);
		$this->GD_copyresampled();
	}
}

class ImageJPG extends ImageBase {
	public function from() {
		$this->image = @imagecreatefromjpeg($this->src);
	}
	public function to($src) {
		imagejpeg($this->image, $src);
	}
}
class ImageJPEG extends ImageJPG {
}
class ImageBMP extends ImageBase {
	public function from() {
		$this->image = @imagecreatefrombmp($this->src);
	}
	public function to($src) {
		imagebmp($this->image, $src);
	}
}

class ImageWEBP extends ImageBase
{
	public function from()
	{
		$this->image = @imagecreatefromwebp($this->src);
	}

	public function to($src)
	{
		imagewebp($this->image, $src);
	}

	public function resize()
	{
		$this->GD_create();
		imagecolortransparent($this->image, imagecolorallocatealpha($this->image, 0, 0, 0, 0));
		imagesavealpha($this->image, true);
		imagealphablending($this->image, false);
		$this->GD_copyresampled();
	}
}

class ImageProcessing {
	private $config;

	public function __construct(array $config) {
		$this->config = $config;
	}

	public function createThumbnail($file, $op) {
		global $board;

		if (!$size = @getimagesize($file->file_path)) {
			error($this->config['error']['invalidimg']);
		}
		if (!in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP, IMAGETYPE_WEBP))) {
			error($this->config['error']['invalidimg']);
		}
		if ($size[0] > $this->config['max_width'] || $size[1] > $this->config['max_height']) {
			error($this->config['error']['maxsize']);
		}

		$image = new Image($file->file_path, $file->extension, $size);
		if ($image->size->width > $this->config['max_width'] || $image->size->height > $this->config['max_height']) {
			$image->delete();
			error($this->config['error']['maxsize']);
		}

		$file->thumb_path = $board['dir'] . $this->config['dir']['thumb'] . $file->file_id . '.' . ($this->config['thumb_ext'] ? $this->config['thumb_ext'] : $file->extension);
		$file->thumb = $file->file_id . '.' . ($this->config['thumb_ext'] ? $this->config['thumb_ext'] : $file->extension);

		if ($this->config['minimum_copy_resize'] &&
			$image->size->width <= $this->config['thumb_width'] &&
			$image->size->height <= $this->config['thumb_height'] &&
			$file->extension == ($this->config['thumb_ext'] ? $this->config['thumb_ext'] : $file->extension)) {

			copy($file->file_path, $file->thumb);

			$file->thumbwidth = $image->size->width;
			$file->thumbheight = $image->size->height;
		} else {
			$thumb = $image->resize(
				$this->config['thumb_ext'] ? $this->config['thumb_ext'] : $file->extension,
				$op ? $this->config['thumb_op_width'] : $this->config['thumb_width'],
				$op ? $this->config['thumb_op_height'] : $this->config['thumb_height']
			);
			$thumb->to($file->thumb_path);

			$file->thumbwidth = $thumb->width;
			$file->thumbheight = $thumb->height;

			$thumb->_destroy();
		}
		return $file;

	}

	public function createWebmThumbnail($file, $op){
		require_once 'inc/lib/webm/ffmpeg.php';
		require_once 'inc/lib/webm/posthandler.php';
		$file->thumb_path = $this->config['dir']['media'] . $file->file_id . '_t' . '.webp';

		$file = set_thumbnail_dimensions($op, $file, $this->config);
		$webminfo = get_webm_info($file->file_path, $this->config);
		make_webm_thumbnail($this->config, $file->file_path, $file->thumb_path, $file->thumbwidth, $file->thumbheight, $webminfo['duration']);
		return $file;
	}

}

class Blockhash
{

	/**
 	* This function compares two binary hashes by calculating the Hamming distance
 	* between them and determining if the number of differing bits is less than the specified threshold.
 	* The comparison is performed directly on the binary data for maximum efficiency.
 	*
 	* @param int    $threshold       The maximum number of differing bits allowed for the hashes to be considered near.
 	* @param string $given_hash      The first binary hash to compare.
 	* @param string $comparison_hash The second binary hash to compare.
 	* @return bool Returns true if the total number of differing bits is less than the threshold, false otherwise.
 	*/
	public static function evaluateBlockhashNearness(int $threshold, string $given_hash, string $comparison_hash): bool
	{

		$diff = $given_hash ^ $comparison_hash;

		$total_difference_value = 0;

		for ($i = 0, $len = strlen($diff); $i < $len; $i++) {
			$total_difference_value += self::count_bits(ord($diff[$i]));

			if ($total_difference_value >= $threshold) {
				return false;
			}
		}
		return $total_difference_value < $threshold;
	}

	/**
 	* Counts the number of 1 bits in the binary representation of a byte.
 	*
 	* This helper function is used to determine the number of differing bits between two bytes.
 	*
 	* @param int $byte The byte whose 1 bits are to be counted.
 	* @return int The number of 1 bits in the byte.
 	*/
	private static function count_bits(int $byte): int
	{
		return substr_count(decbin($byte), '1');
	}

}
