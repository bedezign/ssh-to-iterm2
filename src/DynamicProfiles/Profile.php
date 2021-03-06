<?php

namespace SSHToIterm2\DynamicProfiles;

use SSHToIterm2\SSH\Host;

class Profile
{
    private $guid;
    /** @var Host */
    private $host;
    private $pattern;

    public function __construct(string $pattern, Host $host, string $guid = null)
    {
        $this->pattern = $pattern;
        $this->host    = $host;
        $this->guid    = $guid;
    }

    public function asArray($options, $extraCallbacks = [])
    {
        $profile = [
            'Name' => $name = $this->name(),
            'Guid' => $this->guid(),
        ];

        foreach ($options as $keyword => $action) {
            $lines = $this->host->get($keyword, [], true);
            foreach ($lines as $line) {
                if ($action === false) {
                    continue;
                }
                if (\is_string($action)) {
                    $profile[$action] = $line->valueString;
                } elseif (\is_callable($action)) {
                    $profile = $action($profile, $line, $this->host, $this);
                }
                if (!\is_array($profile)) {
                    throw new \RuntimeException("Processing keyword '$keyword' for host '$name' did not result in an array");
                }
            }
        }

        foreach ($extraCallbacks as $callback) {
            $profile = $callback($profile, $this->host, $this);
        }

        return $profile;
    }

    private function name(): string
    {
        // If we have a label specified, use that
        if ($line = $this->host->get('Label')) {
            return $line->valueString;
        }

        $line = $this->host->get('Host');
        if ($line->hasComment()) {
            return $line->comment;
        }
        return $line->firstValue;
    }

    private function guid(): string
    {
        if (!$this->guid) {
            $this->guid = sha1(implode('', $this->host->patterns));
        }

        return $this->guid;
    }
}