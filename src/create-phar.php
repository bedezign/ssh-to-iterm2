<?php

$baseDir = dirname(__DIR__);
$file = $baseDir . '/ssh-to-iterm2.phar';

// Clean up
if (file_exists($file)) { unlink($file); }
if (file_exists("$file.gz")) { unlink("$file.gz"); }

$phar = new Phar($file);

$iterator = new \RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
$files = new CallbackFilterIterator($iterator, function(SplFileInfo $item, $key, $iterator) {
    if ($item->isDir()) {
        return false;
    }

    $pathName = $item->getPathname();
    // Add everything from the vendor and the src directory, except ourselves
    return $pathName !== __FILE__ && preg_match('@/src|vendor/@', $pathName);
});

echo 'Adding files...', PHP_EOL;
foreach ($files as $file) {
    /** @var SplFileInfo $file */
    $localFile = substr($file->getPathname(), \strlen($baseDir));
    $phar->addFile(realpath($file), $localFile);
    echo "'$file' => $localFile", PHP_EOL;
}

echo 'Setting stub...', PHP_EOL;
$phar->setStub(Phar::createDefaultStub('/src/update.php'));