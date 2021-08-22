<?php

function simpleLog($txt, $type = 'INFO')
{
	try {
		file_put_contents(
			__DIR__ . '/api.txt',
			gmdate('d/m/Y H:i') . ' ' . $type . ': ' . $txt . PHP_EOL . PHP_EOL,
			FILE_APPEND | LOCK_EX
		);
	} catch (Exception $e) {
		echo 'Error saving log: ' . $e->getMessage();
	}

	if ($type == 'ERROR') {
		debug($txt);
	}
}
