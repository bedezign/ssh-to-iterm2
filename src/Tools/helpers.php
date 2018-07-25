<?php

/**
 * Like realpath, but with tilde expansion (Depends on posix functionality being available)
 * @param string $path
 * @param bool   $allowNonExisting
 * @return bool|string
 */
function fixPath($path, $allowNonExisting = false)
{
    // Tilde expansion if needed
    if (\function_exists('posix_getuid') && strpos($path, '~') !== false) {
        $info = posix_getpwuid(posix_getuid());
        $path = str_replace('~', $info['dir'], $path);
    }

    $realPath = \realpath($path);
    if ($allowNonExisting) {
        return $realPath ?: $path;
    }

    return $realPath;
}

/**
 * Split the given string in parts according to whitespace, keeping in mind double quotes
 * @param $string
 * @return array
 */
function splitString($string): array
{
    // https://stackoverflow.com/a/32034603
    return preg_split('/("[^"]*")|\h+/', $string, - 1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
}

/**
 * Returns a ssh keyword-argument string split as an array
 * @param      $string
 * @param bool $doComments Set to true to also process comments instead of ignoring them
 * @return array|null
 */
function splitConfigString($string, $doComments = false): ?array
{
    if ($doComments) {
        // Make sure any preceding comment marker is removed
        $string = preg_replace('/^[\h#]+', '', $string);
    }

    $string = trim($string);
    if ($string[0] === '#') {
        // Comment line, ignore
        return ['key' => null, 'value' => null, 'comments' => trim(ltrim($string, '#'))];
    }

    // First determine the keyword by locating the first space or '='
}