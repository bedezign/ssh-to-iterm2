<?php

namespace SSHToIterm2\SSH;

/**
 * Class Host
 * @package SSHToIterm2\SSH
 *
 * @property-read string[] $patterns
 */
class Host
{
    private $patterns;
    /** @var \SSHToIterm2\SSH\Line[] */
    private $lines = [];
    /** @var bool true if any values were added using a comment line */
    private $commentValues = false;
    /** @var string Originating filename */
    private $file;

    public function __construct($patterns = [], $file = null)
    {
        $this->patterns = $patterns;
        $this->file = $file;
    }

    /**
     * Returns true if at least one of the patterns contains a wildcard
     * @return bool
     */
    public function isWildcardMatch(): bool
    {
        return strpos(implode('', $this->patterns), '*') !== false;
    }

    /**
     * Returns true of the host is a wildcard or multiple patterns match
     * @return bool
     */
    public function isMultiMatch(): bool
    {
        return $this->isWildcardMatch() || \count($this->patterns) > 1;
    }

    public function addLine($line)
    {
        $this->lines[] = $line;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        if ('commentValues' === $name) {
            $this->commentValues = (bool) $value;
        }
    }

    public function get($name, $default = null)
    {
        if (\in_array($name, ['patterns', 'commentValues', 'file'])) {
            return $this->$name;
        }

        foreach ($this->lines as $line) {
            if ($line->is($name)) {
                return $line;
            }
        }
        return $default;
    }

}