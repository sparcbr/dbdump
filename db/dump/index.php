<?php

// TODO: highlight / formatting
// https://prismjs.com/
// https://jush.sourceforge.io/
// http://shjs.sourceforge.net/
// https://www.gnu.org/software/src-highlite/
// http://andre-simon.de/
// http://alexgorbatchev.com/SyntaxHighlighter/
// https://craig.is/making/rainbows
// https://highlightjs.org/
// https://github.com/zeroturnaround/sql-formatter
// https://github.com/google/diff-match-patch

require __DIR__ . '/log.utils.php';
require __DIR__ . '/debug.utils.php';
require __DIR__ . '/file.utils.php';

const CURRENT_GIT_SCHEMA_FILE = __DIR__ . '/../schema.sql';
const SCHEMAS_DIR = __DIR__ . '/../schema';
const CURRENT_SCHEMA_FILE = SCHEMAS_DIR . '/schema.sql';

$dumpPath = __DIR__ . '/dump.sql';
$errors = [];
$savedSchema = '';

$diffInfo = diffSchema($dumpPath);
if ($diffInfo === false) {
	abort('Diff error.');
}

list($currentSchema, $diff) = $diffInfo;

if (!empty($_GET['update']) && !empty($diff)) {
	$savedSchema = saveSchemaDump($dumpPath);
	if (!$savedSchema) {
		abort('Error saving schema file.');
	}
} elseif ($currentSchema == 'none') {
	$errors[] = '<b>No saved schemas found</b>';
}

if (!empty($_GET['download'])) {
	$status = downloadLastSchema();
	if ($status) {
		return;
	}

	http_response_code(404);
}

if (count($errors)) {
	$output .=
		'<div id="error">Errors: <br>' .
		implode('<br>' . PHP_EOL, $errors) .
		'</div>' .
		PHP_EOL;
}

$output .= '<div>' . PHP_EOL;

if ($savedSchema) {
	$output .= 'Schema saved!<br>' . PHP_EOL;
	$currentSchema = $savedSchema;
} elseif (!empty($diff)) {
	$output .= '<textarea>' . implode(PHP_EOL, $diff) . '</textarea>';
	$output .= '<a href="index.php?update=1">Save changes</a><br>' . PHP_EOL;
} else {
	$output .= 'Schema is up to date. No new changes.<br>' . PHP_EOL;
}

if ($currentSchema != 'none') {
$schemaName = basename($currentSchema, '.sql');
	$output .=
		'Download saved schema: <a href="index.php?download=1">' .
		$schemaName .
		'.sql</a><br>' .
		PHP_EOL;
	$output .=
		'Saved on ' .
		gmdate(
			DATE_RSS,
			strtotime(str_replace('-', ' ', $schemaName) . "-000")
		) .
		'<br>' .
		PHP_EOL;
}

$output .= '</div>';

nocache();
echo '<html><head><style>#error { font-weight:bold }</style></head>';
echo '<body>' . $output . '</body></html>';

/*************
 * functions *
 *************/

/**
 * Dump database schema and diff against last saved schema
 *
 * @param string $dumpPath Dump database to this file path
 * @return array filepath to current schema, diff text
 */
function diffSchema($dumpPath)
{
	try {
		dbSchemaDump($dumpPath);

		if (!($currentSchema = getLastSchema())) {
			return false;
			}

		$diffArray = array();
		@exec(
			"diff -uN -I '^-- Date:' $currentSchema $dumpPath 2>&1",
			$diffArray,
			$exitCode
		);

		if ($exitCode == 2) {
			if ($error = error_get_last()) {
				debug($error);
			}
			return false;
		}

		if ($exitCode == 0) {
			// equal files
		}

		return array($currentSchema, $diffArray);
	} catch (\Exception $e) {
		exLog($e, 'DB dump: ');
		return false;
	}
}

/**
 * Create temporary schema dump
 *
 * @param string $dumpPath path to save dump
 */
function dbSchemaDump($dumpPath)
{
	require 'Mysqldump.php';
	require __DIR__ . '/db.config.php';

	$dump = new Ifsnop\Mysqldump\Mysqldump(
		'mysql:host=' .
			DATABASE_SERVER .
			';port=' .
			DATABASE_PORT .
			';dbname=' .
			DATABASE_NAME,
		DATABASE_USERNAME,
		DATABASE_PASSWORD
	);
	$dump->start($dumpPath);
}

/**
 * Save dump with latest schema
 *
 * @param string $filePath source path
 *
 * @return mixed string filepath of saved schema, or false on error
 */
function saveSchemaDump($filePath)
{
	$saveFilePath = SCHEMAS_DIR . '/' . gmdate('Ymd-His') . '.sql';
	if (!@copy($filePath, $saveFilePath)) {
		if ($error = error_get_last()) {
			debug($error);
		}
		return false;
	}

	// need is_link() because file_exists() return false for dangling symlinks
	if (is_link(CURRENT_SCHEMA_FILE) || file_exists(CURRENT_SCHEMA_FILE)) {
		if (!@unlink(CURRENT_SCHEMA_FILE)) {
			if ($error = error_get_last()) {
				debug($error);
			}
			return false;
		}
	}

	if (!@symlink($saveFilePath, CURRENT_SCHEMA_FILE)) {
		if ($error = error_get_last()) {
			debug($error);
		}
		return false;
	}

	if (!@copy($saveFilePath, CURRENT_GIT_SCHEMA_FILE)) {
		debug($saveFilePath . ' to ' . CURRENT_GIT_SCHEMA_FILE);
		if ($error = error_get_last()) {
			debug($error);
		}
	}

	return $saveFilePath;
}

/**
 * Get filename with latest schema
 *
 * @return @false on error, or @string
 */
function getLastSchema()
{
	if (!file_exists(CURRENT_SCHEMA_FILE)) {
		// get last schema from schema dir
		//$lastSchema = glob(SCHEMAS_DIR . '/*.sql');
		exec('ls -td1 ' . SCHEMAS_DIR . '/*.sql', $myarray);
		if (!$lastSchema) {
			return false;
		}

		// compare that (if any) with git schema
		if ($lastSchema == 'none' && !file_exists(CURRENT_GIT_SCHEMA_FILE)) {
			return 'none';
		}

		if (is_link(CURRENT_SCHEMA_FILE)) {
			if (!@unlink(CURRENT_SCHEMA_FILE)) {
				if ($error = error_get_last()) {
					debug($error);
				}
				return false;
			}
		}

		if (!@symlink(CURRENT_GIT_SCHEMA_FILE, CURRENT_SCHEMA_FILE)) {
			if ($error = error_get_last()) {
				debug($error);
			}
			return false;
		}
	}

	if (is_link(CURRENT_SCHEMA_FILE)) {
		$currentSchema = @readlink(CURRENT_SCHEMA_FILE);
	} else {
		$currentSchema = CURRENT_SCHEMA_FILE;
	}

	return $currentSchema;
}

/**
 * Download latest saved schema
 *
 * @return bool
 */
function downloadLastSchema(): bool
{
	if (!($currentSchema = getLastSchema())) {
		return false;
	}
	if ($currentSchema == 'none') {
		return false;
	}

	$schemaName = basename($currentSchema);
	$data = getFileDownload(
		$currentSchema,
		$schemaName,
		'text/plain',
		'attachment'
	);

	if ($data === false || isset($data['error'])) {
		if (isset($data['error'])) {
			abort($data['error']);
		} else {
			abort('Error reading ', $currentSchema);
		}
	}

	sendHeaders($data['headers']);

	echo $data['contents'];
	return true;
}

/**
 * Send headers.
 * From slim. Multiple headers are separated by \n
 */
function sendHeaders($headers)
{
	foreach ($headers as $name => $value) {
		$hValues = explode("\n", $value);
		foreach ($hValues as $hVal) {
			header("$name: $hVal", false);
		}
	}
}

function nocache()
{
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
}

function abort($msg)
{
	http_response_code(500);
	debug('aborting: ' . $msg);
	nocache();
	echo '<br><br>' . $msg . '<br><br><a href="index.php">Index</a>';
	die();
}
