<?php

namespace SSHToIterm2\DynamicProfiles;

use SSHToIterm2\SSH\Host;

class Collection
{
    /** @var Profile[] */
    private $profiles = [];

    public function addProfile(string $pattern, Host $host)
    {
        // @TODO Load the GUID from the existing profile
        $guid = /*isset($this->profiles[$pattern]) ? null :*/ null;
        $this->profiles[$pattern] = new Profile($pattern, $host, $guid);
    }

    public function write($path, $options, $extraCallbacks = [])
    {
        $profiles = [];

        foreach ($this->profiles as $profile) {
            $array = $profile->asArray($options, $extraCallbacks);
            $profiles[] = $array;
        }

        $json = json_encode(['Profiles' => $profiles], JSON_PRETTY_PRINT);
        if ($path) {
            $handle = fopen($path, 'w');
            fwrite($handle, $json);
            fclose($handle);
        }
        else {
            echo $json, PHP_EOL, PHP_EOL;
        }
    }
}