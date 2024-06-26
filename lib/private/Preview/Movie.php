<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Preview;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IImage;
use Psr\Log\LoggerInterface;

class Movie extends ProviderV2 {
	/**
	 * @deprecated 23.0.0 pass option to \OCP\Preview\ProviderV2
	 * @var string
	 */
	public static $avconvBinary;

	/**
	 * @deprecated 23.0.0 pass option to \OCP\Preview\ProviderV2
	 * @var string
	 */
	public static $ffmpegBinary;

	/** @var string */
	private $binary;

	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/video\/.*/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAvailable(FileInfo $file): bool {
		// TODO: remove when avconv is dropped
		if (is_null($this->binary)) {
			if (isset($this->options['movieBinary'])) {
				$this->binary = $this->options['movieBinary'];
			} elseif (is_string(self::$avconvBinary)) {
				$this->binary = self::$avconvBinary;
			} elseif (is_string(self::$ffmpegBinary)) {
				$this->binary = self::$ffmpegBinary;
			}
		}
		return is_string($this->binary);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		// TODO: use proc_open() and stream the source file ?

		if (!$this->isAvailable($file)) {
			return null;
		}

		$result = null;
		if ($this->useTempFile($file)) {
			// try downloading 5 MB first as it's likely that the first frames are present there
			// in some cases this doesn't work for example when the moov atom is at the
			// end of the file, so if it fails we fall back to getting the full file
			$sizeAttempts = [5242880, null];
		} else {
			// size is irrelevant, only attempt once
			$sizeAttempts = [null];
		}

		foreach ($sizeAttempts as $size) {
			$absPath = $this->getLocalFile($file, $size);

			$result = null;
			if (is_string($absPath)) {
				$result = $this->generateThumbNail($maxX, $maxY, $absPath, 5);
				if ($result === null) {
					$result = $this->generateThumbNail($maxX, $maxY, $absPath, 1);
					if ($result === null) {
						$result = $this->generateThumbNail($maxX, $maxY, $absPath, 0);
					}
				}
			}

			$this->cleanTmpFiles();

			if ($result !== null) {
				break;
			}
		}

		return $result;
	}

	private function generateThumbNail(int $maxX, int $maxY, string $absPath, int $second): ?IImage {
		$tmpPath = \OC::$server->getTempManager()->getTemporaryFile();
		$binaryType = substr(strrchr($this->binary, '/'), 1);
		$cmd = $this->buildCommand($binaryType, $second, $absPath, $tmpPath);

		if (!$cmd) {
			unlink($tmpPath);
			return null;
		}

		$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		if (!is_resource($proc)) {
			unlink($tmpPath);
			return null;
		}

		$output = $this->processPipes($pipes);
		$status = proc_get_status($proc);  //Get process status information

		if ($status['running']) {
			proc_terminate($proc, 9); // SIGKILL
		}
		$returnCode = proc_close($proc);

		if ($returnCode === 0) {
			return $this->handleSuccessfulThumbnailCreation($tmpPath, $maxX, $maxY);
		}

		$this->logError($second, $output);
		unlink($tmpPath);
		return null;
	}

	private function buildCommand(string $binaryType, int $second, string $absPath, string $tmpPath): ?array {
		if ($binaryType === 'avconv' || $binaryType === 'ffmpeg') {
			// Faster seeking for ffmpeg
			$fastSeek = ($binaryType === 'ffmpeg') ? ['-ss', (string)$second, '-i', $absPath] : ['-i', $absPath, '-ss', (string)$second];
			return array_merge([$this->binary, '-y'], $fastSeek, ['-an', '-f', 'mjpeg', '-vframes', '1', '-vsync', '1', $tmpPath]);
		}
		return null;
	}

	private function processPipes(array $pipes): string {
		$output = "";
		foreach ([1, 2] as $pipeNum) {
			stream_set_blocking($pipes[$pipeNum], false); // Set to non-blocking mode
			stream_set_timeout($pipes[$pipeNum], 10);
			while (!feof($pipes[$pipeNum])) {
				$chunk = fread($pipes[$pipeNum], 8192);
				if ($chunk === false || $chunk === "") {
					$info = stream_get_meta_data($pipes[$pipeNum]);
					if ($info['timed_out'] || $info['eof']) {
						break;  // Exit the loop if timeout or file ends
					}
				}
				$output .= $chunk;
			}
			fclose($pipes[$pipeNum]);
		}
		return $output;
	}

	private function handleSuccessfulThumbnailCreation(string $tmpPath, int $maxX, int $maxY): ?IImage {
		$image = new \OCP\Image();
		$image->loadFromFile($tmpPath);
		if ($image->valid()) {
			unlink($tmpPath);
			$image->scaleDownToFit($maxX, $maxY);
			return $image;
		}
		return null;
	}

	private function logError(int $second, string $output): void {
		$logger = \OC::$server->get(LoggerInterface::class);
		$logger->info('Thumbnail generation failed at second {second}. Output: {output}', ['app' => 'core', 'second' => $second, 'output' => $output]);
	}
}
