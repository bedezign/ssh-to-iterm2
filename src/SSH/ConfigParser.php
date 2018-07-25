<?php

namespace SSHToIterm2\SSH;

class ConfigParser
{
    private $file;
    private $hosts           = [];
    private $commentKeywords = [];

    /**
     * @param string $file
     * @param array  $commentKeywords Keywords that are recognized as a keyword=value if found as a comment
     */
    public function __construct(string $file, $commentKeywords = [])
    {
        $this->file            = $file;
        $this->commentKeywords = $commentKeywords;
    }

    /**
     * @return \SSHToIterm2\SSH\Host[]
     */
    public function hosts(): \Generator
    {
        $this->parse();
        foreach ($this->hosts as $host) {
            yield $host;
        }
    }

    /**
     * Simple parsing function, this just locates all host entries and assigns
     * all following lines to them.
     */
    private function parse()
    {
        $this->hosts = [];

        $lines        = $this->read();
        $host         = null;
        $fromComments = 0;
        foreach ($lines as $string) {
            $line = new Line($string);
            if ($line->is('Host')) {
                if ($host) {
                    $host->commentValues = 0 !== $fromComments;
                    $this->hosts[]      = $host;
                }
                $host         = new Host($line->value);
                $fromComments = 0;
            }

            // We can't do anything with this line unless we have an active host
            if ($host) {
                // If the line is a comment, see if we can re-parse that comment into a recognised option
                if ($line->isComment()) {
                    $newLine = new Line($line->comment);
                    if (\in_array($newLine->keyword, $this->commentKeywords)) {
                        $fromComments ++;
                        $line = $newLine;
                    }
                }

                // Add the line to it (this will also add the Host line itself)
                $host->addLine($line);
            }
        }
    }

    /**
     * Reads the specified file into an array, replacing all Include statements with their content
     * @param string|null $file
     * @return string[]
     */
    private function read($file = null): array
    {
        if (null === $file) {
            $file = $this->file;
        }

        $result = [];

        $lines = file(fixPath($file));
        foreach ($lines as $line) {
            $line = trim($line);

            if (stripos($line, 'include') === 0) {
                // Currently assume there will be only one file included.
                foreach (splitString(substr($line, 7)) as $includeFile) {
                    $result = array_merge($result, $this->read($includeFile));
                }
            }
            $result[] = $line;
        }

        return $result;
    }
}