<?php

namespace Vichan\Data;

use Vichan\Data\Driver\LogDriver;

class FileSystem {

	/**
	 * Move a file from its old path to a new path.
	 *
	 * @param string $oldPath The original file path.
	 * @param string $newPath The new file path.
	 * @return bool true on success, false on failure.
	 */
	public static function moveFile(string $oldPath, string $newPath, LogDriver $logger): bool {
		if (@\rename($oldPath, $newPath)) {
			return true;
		}

		$logger->log(
			LogDriver::ERROR,
			"Couldn't move file {$oldPath} to {$newPath}: " . error_get_last()['message'] ?? 'Unknown error'
		);
		return false;
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path The file path.
	 * @return bool true on success, false on failure.
	 */
	public static function deleteFile(string $path, LogDriver $logger): bool {
		if (\file_unlink($path)) {
			return true;
		}

		$logger->log(
			LogDriver::ERROR,
			"Couldn't delete file $path: " . error_get_last()['message'] ?? 'Unknown error'
		);
		return false;
	}

	/**
	* Process a file and move it from directories.
	*
	* @param array $file The file array to be processed.
	* @param string $oldBasePath Where the file is at.
	* @param string $newBasePath New path where the file should be.
	* @return array Returns modified $files array with updated paths.
	*/
	public static function batchMoveFiles(LogDriver $logger, array $files, string $oldBasePath, string $newBasePath): array {
		$updatedFiles = [];

		foreach ($files as $file) {
			if ($file['file'] === 'deleted') {
				$updatedFiles[] = $file;
				continue;
			}

			$oldPath = $oldBasePath . $file['file'];
			$newPath = $newBasePath . $file['file'];
			if (self::moveFile($oldPath, $newPath, $logger)) {
				$file['file_path'] = $newPath;
			}

			if (isset($file['thumb']) && !\in_array($file['thumb'], ['spoiler', 'file', 'deleted'])) {
				$oldThumbPath = $oldBasePath . $file['thumb'];
				$newThumbPath = $newBasePath . $file['thumb'];
				if (self::moveFile($oldThumbPath, $newThumbPath, $logger)) {
					$file['thumb_path'] = $newThumbPath;
				}
			}

			$updatedFiles[] = $file;
		}

		return $updatedFiles;
	}

	/**
	* Handles file operations for shadow delete based on the operation type.
	*
	* @param array $file The file array to be processed.
	* @param string $operation The type of operation to perform: 'delete', 'restore', or 'purge'.
	* @param string $media The Media folder.
	* @param string $shadow The Shadow folder.
	* @param string $seed The Seed for hashing.
	* @return void
	*/
	public static function batchMoveFilesShadow(
		LogDriver $logger,
		array $files,
		string $operation,
		string $media,
		string $shadow,
		string $seed
	): void {
		foreach ($files as $file) {
			if ($file['file'] === 'deleted') {
				continue;
			}

			$originalFile = $media . $file['file'];
			$shadowFile = $shadow . self::hashShadowDelFilename($file['file'], $seed);

			$originalThumb = '';
			$shadowThumb = '';

			if (isset($file['thumb']) && !\in_array($file['thumb'], ['spoiler', 'file', 'deleted'])) {
				$originalThumb = $media . $file['thumb'];
				$shadowThumb = $shadow . self::hashShadowDelFilename($file['thumb'], $seed);
			}

			switch ($operation) {
				case 'delete':
					self::moveFile($originalFile, $shadowFile, $logger);
					self::moveFile($originalThumb, $shadowThumb, $logger);
					break;
				case 'restore':
					self::moveFile($shadowFile, $originalFile, $logger);
					self::moveFile($shadowThumb, $originalThumb, $logger);
					break;
				case 'purge':
					self::deleteFile($shadowFile, $logger);
					self::deleteFile($shadowThumb, $logger);
					break;
				default:
					break;
			}
		}
	}

	/**
	* Hash filenames to obscure filenames.
	*
	* @param string $filename The original filename.
	* @param string $seed The seed for hashing.
	* @return string Returns hashed filename.
	*/
	private static function hashShadowDelFilename(string $filename, string $seed): string {
		if (\in_array($filename, ['deleted', 'spoiler', 'file'], true)) {
			return $filename;
		}

		$file = \pathinfo($filename);
		return \sha1($file['filename'] . $seed) . "." . ($file['extension'] ?? '');

	}

	/**
	* Hash filenames of a json string.
	*
	* @param string $filename The original json of filenames.
	* @param string $seed The seed for hashing.
	* @return string Returns modified json with hashed filenames.
	*/
	public static function hashShadowDelFilenamesDBJSON(string $files_db_json, string $seed): string {
		$files_new = [];
		foreach (\json_decode($files_db_json) as $f) {
			$f->file = self::hashShadowDelFilename($f->file, $seed);
			$f->thumb = self::hashShadowDelFilename($f->thumb, $seed);
			$files_new[] = $f;
		}
		return \json_encode($files_new);

	}
}