<?php
const DEBUG_LOG_FILE = __DIR__ . '/debug.txt';
const ROOT_DIR = __DIR__;

function exLog($e, $val = '')
{
	if ($e instanceof Exception) {
		$str = empty($val) ? '' : $val . PHP_EOL;
		$str .= sprintf(
			"%s: (%d): %s\n",
			get_class($e),
			$e->getCode(),
			$e->getMessage()
		);

		//APIGeneralLog($str, 'Exception');

		debugLog($str . traceToStr($e->getTrace()));
	}
}

function debug($val, $count = 4, $start = 0)
{
	$limit = $count + $start;
	$backTraceOpts = 0;
	$backTraceOpts = DEBUG_BACKTRACE_IGNORE_ARGS;
	//$backTraceOpts |= DEBUG_BACKTRACE_PROVIDE_OBJECT;
	$backTrace = debug_backtrace($backTraceOpts, $limit);

	$str = PHP_EOL . print_r($val, true) . PHP_EOL;

	$str .= traceToStr($backTrace);

	debugLog($str);
}

function traceToStr($trace)
{
	$str = '';
	for ($i = 0; $i < count($trace); $i++) {
		$btLine = $trace[$i];
		if ($btLine['function'] == '{closure}') {
			break;
		}
		$func = '';
		if (isset($btLine['function'])) {
			if (isset($btLine['class'])) {
				$func = $btLine['class'] . $btLine['type'];
			}
			$func .= $btLine['function'];
		}
		$str .= sprintf(
			"%3s %20s() at %s:%d\n",
			"#$i",
			$func,
			isset($btLine['file'])
				? preg_replace('|' . ROOT_DIR . '/|', '', $btLine['file'])
				: '',
			$btLine['line'] ?? ''
		);
	}
	return $str;
}

function debugLog($txt, $type = 'DEBUG')
{
	try {
		file_put_contents(
			DEBUG_LOG_FILE,
			gmdate('Y-m-d H:i') . ' ' . $type . ': ' . $txt . PHP_EOL,
			FILE_APPEND | LOCK_EX
		);
	} catch (Exception $e) {
		// shouldn't happen
	}
}
