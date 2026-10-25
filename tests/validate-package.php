<?php

function fail_test($message)
{
	fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
	exit(1);
}

function load_xml($path, $context)
{
	if (!is_file($path))
		fail_test($context . ' does not exist');

	$document = new DOMDocument();
	if (!$document->load($path, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR))
		fail_test('could not load ' . $context);

	return $document;
}

function replace_once($source, $search, $replacement, $context)
{
	$count = 0;
	$result = str_replace($search, $replacement, $source, $count);

	if ($count !== 1)
		fail_test($context . ' expected exactly one replacement, found ' . $count);

	return $result;
}

function assert_php53_compatible($code, $context)
{
	$tokens = token_get_all('<?php ' . $code);
	$previous_can_end_expression = false;

	foreach ($tokens as $token)
	{
		if (is_array($token))
		{
			$id = $token[0];
			$value = $token[1];

			if ($id === T_OPEN_TAG || $id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT)
				continue;
		}
		else
		{
			$id = null;
			$value = $token;
		}

		if ($value === '[' && !$previous_can_end_expression)
			fail_test($context . ' uses PHP short array syntax, which is not compatible with PHP 5.3');

		$previous_can_end_expression = $id === T_VARIABLE || $id === T_STRING || $id === T_CONSTANT_ENCAPSED_STRING || $id === T_LNUMBER || $id === T_DNUMBER || $id === T_TRUE || $id === T_FALSE || $id === T_NULL || ($id === null && in_array($value, array(')', ']', '}'), true));
	}

	if (strpos($code, '??') !== false || strpos($code, '<=>') !== false)
		fail_test($context . ' uses syntax newer than PHP 5.3');
}

function validate_modification($document, $namespace, $context)
{
	$files = $document->getElementsByTagNameNS($namespace, 'file');
	if ($files->length === 0)
		fail_test($context . ' contains no file elements');

	$total_operations = 0;
	for ($file_index = 0; $file_index < $files->length; $file_index++)
	{
		$file = $files->item($file_index);
		$file_name = $file->getAttribute('name');
		if ($file_name === '')
			$file_name = 'file #' . ($file_index + 1);

		$operations = $file->getElementsByTagNameNS($namespace, 'operation');
		if ($operations->length === 0)
			fail_test($file_name . ' contains no modification operations');

		for ($operation_index = 0; $operation_index < $operations->length; $operation_index++)
		{
			$operation = $operations->item($operation_index);
			$context_name = $file_name . ', operation #' . ($operation_index + 1);
			$search_nodes = $operation->getElementsByTagNameNS($namespace, 'search');
			$add_nodes = $operation->getElementsByTagNameNS($namespace, 'add');

			if ($search_nodes->length !== 1 || $add_nodes->length !== 1)
				fail_test($context_name . ' must contain exactly one search and one add element');

			$search_node = $search_nodes->item(0);
			$search = $search_node->textContent;
			$add = $add_nodes->item(0)->textContent;
			$position = strtolower(trim($search_node->getAttribute('position')));
			if ($position === '')
				$position = 'replace';

			if (!in_array($position, array('replace', 'before', 'after'), true))
				fail_test($context_name . ' uses unsupported search position "' . $position . '"');
			if (trim($search) === '')
				fail_test($context_name . ' has an empty search anchor');

			assert_php53_compatible($add, $context_name . ' add block');

			$fixture = "<?php\n" . $search . "\n";
			if ($position === 'replace')
				$installed = replace_once($fixture, $search, $add, $context_name);
			elseif ($position === 'before')
				$installed = replace_once($fixture, $search, $add . $search, $context_name);
			else
				$installed = replace_once($fixture, $search, $search . $add, $context_name);

			if ($position === 'replace')
				$restored = replace_once($installed, $add, $search, $context_name . ' during reverse');
			elseif ($position === 'before')
				$restored = replace_once($installed, $add . $search, $search, $context_name . ' during reverse');
			else
				$restored = replace_once($installed, $search . $add, $search, $context_name . ' during reverse');

			if ($restored !== $fixture)
				fail_test($context_name . ' is not reversible');

			$total_operations++;
		}
	}

	return $total_operations;
}

$root = dirname(dirname(__FILE__));
$package_namespace = 'http://www.simplemachines.org/xml/package-info';
$modification_namespace = 'http://www.simplemachines.org/xml/modification';

$package = load_xml($root . '/package-info.xml', 'package-info.xml');
$package_versions = $package->getElementsByTagNameNS($package_namespace, 'version');
if ($package_versions->length !== 1)
	fail_test('package-info.xml must contain exactly one package version');
$version = trim($package_versions->item(0)->textContent);

if (!preg_match('/^[0-9]+(\.[0-9]+)+$/', $version))
	fail_test('package version "' . $version . '" is not a numeric dotted version');

$smf2_modification = load_xml($root . '/SMF2/install.xml', 'SMF2/install.xml');
$smf2_versions = $smf2_modification->getElementsByTagNameNS($modification_namespace, 'version');
if ($smf2_versions->length !== 1)
	fail_test('SMF2/install.xml must contain exactly one modification version');
if (trim($smf2_versions->item(0)->textContent) !== $version)
	fail_test('package-info.xml and SMF2/install.xml versions do not match');

$smf1_mod_path = $root . '/SMF1/tinyportal1090.mod';
$smf1_mod = file_get_contents($smf1_mod_path);
if ($smf1_mod === false)
	fail_test('could not read SMF1/tinyportal1090.mod');
if (!preg_match('/<version>\s*([^<]+?)\s*<\/version>/i', $smf1_mod, $smf1_version))
	fail_test('SMF1/tinyportal1090.mod has no version');
if (trim($smf1_version[1]) !== $version)
	fail_test('package-info.xml and SMF1/tinyportal1090.mod versions do not match');

$required_directories = $package->getElementsByTagNameNS($package_namespace, 'require-dir');
for ($i = 0; $i < $required_directories->length; $i++)
{
	$name = trim($required_directories->item($i)->getAttribute('name'));
	if ($name === '' || !is_dir($root . '/' . $name))
		fail_test('required directory is missing: ' . $name);
}

foreach (array('modification', 'database', 'code') as $tag_name)
{
	$references = $package->getElementsByTagNameNS($package_namespace, $tag_name);
	for ($i = 0; $i < $references->length; $i++)
	{
		$path = trim($references->item($i)->textContent);
		if ($path !== '' && !is_file($root . '/' . $path))
			fail_test('package reference is missing: ' . $path);
	}
}

$operations = validate_modification($smf2_modification, $modification_namespace, 'SMF2/install.xml');

echo 'PASS: package XML, version consistency, required paths, PHP 5.3 compatibility, ' . $operations . ' reversible SMF2 operations' . PHP_EOL;

?>
