<?php

namespace SSHToIterm2\Console;

use SSHToIterm2\DynamicProfiles\Collection;
use SSHToIterm2\SSH\ConfigParser;
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
            ->addOption('bind', 'b', InputOption::VALUE_NONE, 'Fill the <info>Bound Hosts</info> list with all patterns and the hostname. (see "Automatic Profile Switching" in Profile > Advanced)');
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
            $save ? fixPath(rtrim($input->getOption('directory'), '\\/'). '/' . ($input->getOption('save') ?? self::FILENAME), true) : false,
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
            'CustomCommand' => function($profile, $host, $array) {
                if (!array_key_exists('Command', $array)) {
                    // Use the first pattern to connect instead of hostname (on the chance that hostname is not a part of
                    // the config section and will not be assigned the correct options by ssh)
                    // Possible improvement: Iterate through patters to make sure we use a non-wildcard one
                    $hostname = $host->patterns[0];
                    $array['Custom Command'] = 'Yes';
                    $array['Command'] = 'ssh ' . $hostname;
                }
                return $array;
            }
        ];

        if ($input->getOption('bind')) {
            $callbacks['BoundHosts'] = function($profile, $host, $array) {
                $array['Bound Hosts'] = $host->get('Host')->value;
                if ($hostname = $host->get('Hostname')) {
                    $array['Bound Hosts'][] = $hostname->valueString;
                }
                $array['Bound Hosts'] = array_unique($array['Bound Hosts']);
                return $array;
            };
        }

        return $callbacks;
    }
}
