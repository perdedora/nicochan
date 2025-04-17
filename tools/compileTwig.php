<?php

require dirname(__FILE__) . '/inc/cli.php';

load_twig();
$tplDir = $config['dir']['template'];
$tmpDir =  getcwd() . '/compiled_templates/';

if (!is_dir($tmpDir)) {
	mkdir($tmpDir, 0777);
} else {
	array_map('unlink', glob("{$tmpDir}*"));
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tplDir), RecursiveIteratorIterator::LEAVES_ONLY) as $file)
{
	if ($file->isFile() && $file->getExtension() === 'html') {
		$filename = str_replace($tplDir . '/', '', $file);
		$source = $twig->getLoader()->getSourceContext($filename);
		echo "Compiling $filename \n";
		$path = $tmpDir . str_replace('/', '_', $filename) . ".php";
		file_write($path, $twig->compileSource($source));
	}

}
