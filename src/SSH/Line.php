<?php

namespace SSHToIterm2\SSH;

/**
 * Class Line
 * @package SSHToIterm2\SSH
 *
 * @property-read string|null   $keyword
 * @property-read string|null   $valueString
 * @property-read string[]|null $value
 * @property-read string|null   $comment
 * @property-read string        $line
 */
class Line
{
    private $keyword;
    private $value;
    private $valueString;
    private $comment;
    private $line;

    public function __construct(string $line)
    {
        $this->line = $line;
        $this->parse($line);
    }

    public function is($keyword)
    {
        return $this->keyword === strtolower($keyword);
    }

    /**
     * Returns true if the line is a comment line (either empty or only containing a comment)
     * @param bool $allowEmpty  If true, an empty line counts as a comment
     * @return bool
     */
    public function isComment($allowEmpty = false): bool
    {
        return ($this->comment || ($allowEmpty && '' === $this->line)) && !$this->keyword && !$this->value;
    }

    public function hasKeyword()
    {
        return null !== $this->keyword;
    }

    /**
     * Returns true if the line contains a comment
     * @return bool
     */
    public function hasComment(): bool
    {
        return null !== $this->comment;
    }

    public function __get($name)
    {
        if (\in_array($name, ['keyword', 'value', 'valueString', 'comment', 'line'])) {
            if ($name === 'value') {
                reset($this->value);
            }
            return $this->$name;
        }

        if ('firstValue' === $name) {
            return reset($this->value);
        }

        return null;
    }

    private function parse($line)
    {
        // Split off comments first
        $parts = explode('#', trim($line), 2);
        if (\count($parts) === 2) {
            $this->comment = trim($parts[1]);
            $line          = rtrim($parts[0]);
        }

        if (preg_match('/[\h=]/', $line, $matches, PREG_OFFSET_CAPTURE)) {
            $offset        = $matches[0][1];
            $this->keyword = strtolower(substr($line, 0, $offset));

            // Get rid of spaces and the '='
            $this->valueString = ltrim(substr($line, $offset), " \t\n\r\0\x0B=");
            $this->value       = array_map(
                function($value) {
                    return trim($value, " \t\n\r\0\x0B\"");
                },
                preg_split('/("[^"]*")|\h+/', $this->valueString, - 1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)
            );
        }
    }
}