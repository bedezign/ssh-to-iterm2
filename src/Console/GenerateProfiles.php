<?php

namespace SSHToIterm2\Console;

use SSHToIterm2\DynamicProfiles\Collection;
use SSHToIterm2\SSH\ConfigParser;
use SSHToIterm2\SSH\Line;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateProfiles extends Command
{
    private const DIRECTORY = '~/Library/Application Support/iTerm2/DynamicProfiles';
    private const FILENAME  = 'ssh-config.json';

    private $options;
    private $profiles;

    protected function configure()
    {
        $this
            ->setName('generate')
            ->setDescription('Generates iTerm2 dynamic profiles from SSH config')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'SSH config file to use', '~/.ssh/config')
            ->addOption('save', 's', InputOption::VALUE_OPTIONAL, 'Save the profiles to "file" instead of STDOUT', self::FILENAME)
            ->addOption('uncommented', 'u', InputOption::VALUE_NONE, 'Also include hosts that do not have at least one profile-related comment')
            ->addOption('wildcard', 'w', InputOption::VALUE_NONE, 'Include hosts with a wildcard pattern instead of skipping them')
            ->addOption('multi-pattern', 'm', InputOption::VALUE_OPTIONAL, 'Behavior for hosts with multiple patterns. Use the <info>first</info> only, use <info>all</info> or <info>ignore</info> the host.', 'first')
            ->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'Path to iTerm2\'s dynamic profiles (when using <info>--save</info>)', self::DIRECTORY)
            ->addOption('bind', 'b', InputOption::VALUE_NONE, 'Fill the <info>Bound Hosts</info> list with all patterns and the hostname. (see "Automatic Profile Switching" in Profile > Advanced)')
            ->addOption('autotag', 't', InputOption::VALUE_REQUIRED, 'Automatically add tags for hosts (see README.md for formatting options)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ignoreWildcards = !$input->getOption('wildcard');
        $commentedOnly   = !$input->getOption('uncommented');
        $multiPattern    = $input->getOption('multi-pattern');

        foreach ($this->getConfig($input)->hosts() as $host) {

            // If needed, skip:
            if (
                // There were no commented profile entries and we should skip those
                ($commentedOnly && !$host->commentValues) ||
                // Skip hosts with wildcards in their pattern if requested
                ($ignoreWildcards && $host->isWildcardMatch()) ||
                // If the host itself has an "ignore" entry
                $host->get('ignore')) {
                continue;
            }

            // See which patterns we are to keep
            $patterns = $host->patterns;
            if ($host->isMultiMatch()) {
                // Ignore multi patterned hosts?
                if ($multiPattern === 'ignore') {
                    continue;
                }

                // first only ("all" assume otherwise)
                if ($multiPattern === 'first') {
                    $patterns = [reset($patterns)];
                }
            }

            // Create profiles
            foreach ($patterns as $pattern) {
                $this->getProfiles()->addProfile($pattern, $host);
            }

        }

        // Since "save" has a default value we can't detect if it was actually specified by looking at the value, so fall back to hasParameterOption for that
        $save = $input->hasParameterOption('--save', true) || $input->hasParameterOption('-s', true);
        $this->getProfiles()->write(
            $save ? fixPath(rtrim($input->getOption('directory'), '\\/') . '/' . ($input->getOption('save') ?? self::FILENAME), true) : false,
            $this->getOptionConfiguration(), $this->getCallbacks($input)
        );
    }

    /**
     * Returns the list of all iTerm2 recognized comment keywords and how they should be included in the profile
     * @return array
     */
    private function getOptionConfiguration(): array
    {
        if (!$this->options) {
            $this->options = require __DIR__ . '/../DynamicProfiles/options.php';
        }
        return $this->options;
    }

    /**
     * Returns a list of comment keywords that should be recognised as configuration
     * @return array
     */
    private function getCommentKeywords(): array
    {
        return array_map('strtolower', array_keys($this->getOptionConfiguration()));
    }

    /**
     * @return Collection
     */
    private function getProfiles(): Collection
    {
        if (!$this->profiles) {
            $this->profiles = new Collection();
        }
        return $this->profiles;
    }


    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @return \SSHToIterm2\SSH\ConfigParser
     */
    private function getConfig(InputInterface $input): ConfigParser
    {
        return new ConfigParser($input->getOption('config'), $this->getCommentKeywords());
    }

    /**
     * Return a list of extra callbacks for the profile generation
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @return array
     */
    private function getCallbacks(InputInterface $input): array
    {
        $callbacks = [
            // If no custom command was specified add one. We can safely assume all hosts are SSH hosts I think...
            // Note that if the first pattern is a wildcard pattern, this won't work
            'CustomCommand' => function($array, $host) {
                if (!array_key_exists('Command', $array)) {
                    // Use the first pattern to connect instead of hostname (on the chance that hostname is not a part of
                    // the config section and will not be assigned the correct options by ssh)
                    // Possible improvement: Iterate through patters to make sure we use a non-wildcard one
                    $hostname                = $host->patterns[0];
                    $array['Custom Command'] = 'Yes';
                    $array['Command']        = 'ssh ' . $hostname;
                }
                return $array;
            }
        ];

        if ($input->getOption('bind')) {
            $callbacks['BoundHosts'] = function($array, $host) {
                $array['Bound Hosts'] = $host->get('Host')->value;
                if ($hostname = $host->get('Hostname')) {
                    $array['Bound Hosts'][] = $hostname->valueString;
                }
                $array['Bound Hosts'] = array_unique($array['Bound Hosts']);
                return $array;
            };
        }

        if ($autoTags = $input->getOption('autotag')) {
            $autoTags             = new Line('AutoTag ' . $autoTags);
            $callbacks['AutoTag'] = function($array, $host) use ($autoTags) {
                $tags = array_key_exists('Tags', $array) ? $array['Tags'] : [];

                // Assemble substitutes
                $substitutes = [
                    '%h' => current($host->get('Host')->value),
                    '%p' => $host->get('Host')->value,
                    '%l' => implode(',', $host->get('Host')->value),
                    '%f' => basename($host->file),
                    '%d' => basename(\dirname($host->file)),
                ];

                // Create an uppercase variant of all and make a list of the ones that have array'ed values
                $arrays = [];
                foreach ($substitutes as $i => $v) {
                    $u = strtoupper($i);
                    if (\is_array($v)) {
                        $arrays[] = $i;
                        $arrays[] = $u;
                        $substitutes[$u] = array_map('strtoupper', $v);
                    } else {
                        $substitutes[$u] = strtoupper($v);
                    }
                }
                $arrayPattern = '/(' . implode('|', array_map(function($value) { return str_replace(['|', '/'], ['\|', '\/'], $value); }, $arrays)) . ')/';

                // Eliminate all array values, replace by a comma separated list
                $substitutesFlat = $substitutes;
                array_walk($substitutesFlat, function(&$value) { $value = \is_array($value) ? implode(',', $value) : $value; });

                // Add the tags
                foreach ($autoTags->value as $tag) {
                    $copy = $substitutesFlat;

                    // Fake loop once for everything
                    $iterator = key($copy);
                    $values = [current($copy)];

                    if (preg_match($arrayPattern, $tag, $matches)) {
                        // One of our array'ed values? Then we'll loop for real
                        $iterator = $matches[0];
                        $values = $substitutes[$matches[0]];
                    }

                    foreach ($values as $value) {
                        $copy[$iterator] = $value;
                        $tags[] = str_replace(array_keys($copy), array_values($copy), $tag);
                    }
                }

                if (\count($tags)) {
                    $array['Tags'] = $tags;
                }
                return $array;
            };
        }

        return $callbacks;
    }
}
