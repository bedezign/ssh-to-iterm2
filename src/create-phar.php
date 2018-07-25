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
    // Add the files without whitespace to make the regular phar as small as possible already
    $phar->addFromString($localFile, php_strip_whitespace(realpath($file)));
    echo "'$file' => $localFile", PHP_EOL;
}

echo 'Creating stub...', PHP_EOL;
// Compressing a phar overrides the stub, forcing /index.php to be used as bootstrap.
// So as a solution just add a dummy file that loads the actual bootstrap.
# $phar->setStub(Phar::createDefaultStub('/src/index.php'));
$phar->addFromString('/index.php', '<?php require __DIR__ . "/src/index.php";');

echo 'Compressing...', PHP_EOL;
$phar->compress(Phar::GZ);