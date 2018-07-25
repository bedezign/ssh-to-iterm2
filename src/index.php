<?php

namespace SSHToIterm2;

require __DIR__ . '/../vendor/autoload.php';

use SSHToIterm2\Console\GenerateProfiles;
use Symfony\Component\Console\Application;

$command = new GenerateProfiles();
$application = new Application();
$application->add($command);
$application->setDefaultCommand($command->getName(), true);
$application->run();
