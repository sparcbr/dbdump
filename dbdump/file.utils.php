<?php

function readFileData($filePath, $validMimes = '')
{
	if (!file_exists($filePath)) {
		simpleLog('File ' . $filePath . ' does not exist.', 'ERROR');
		return ['error' => 'File does not exist'];
	}

	$length = filesize($filePath);
	if (!$length) {
		simpleLog('File ' . $filePath . ': filesize() error.', 'ERROR');
		if ($error = error_get_last()) {
			debug($error);
		}
		return ['error' => 'Zero size'];
	}

	$mime = mime_content_type($filePath);
	$contents = file_get_contents($filePath);
	if ($contents === false) {
		simpleLog('File ' . $filePath . ' is not readable.', 'ERROR');
		return ['error' => 'File read error'];
	}

	return [
		'contents' => $contents,
		'mime' => $mime,
		'length' => $length
	];
}

function getFileDownload($filePath, $name, $validMimes, $disposition = 'inline')
{
	$data = readFileData($filePath, $validMimes);

	if ($data === false || isset($data['error'])) {
		return $data;
	}

	$data['headers'] = [
		'Content-Type' => $data['mime'],
		'Content-Disposition' => $disposition . '; filename="' . $name . '"',
		'Content-Length' => $data['length'],
		'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
		'Pragma' => 'no-cache',
		'Content-Transfer-Encoding' => 'attachment',
		'Connection' => 'close'
	];

	return $data;
}
