<?php
/**
 * This file contains everything that is recognised in the SSH Config files as usable for the iTerm2
 * Dynamic Profiles.
 * The key is what is expected in the SSH Config.
 * The value is either
 * - false: load it, but don't do anything with it when saving)
 * - string: Save the value - as a string - under this name in the profile
 * - closure/callable: Receive the Profile instance and the arrayed version, update the array and return the updated version
 *                     You can use this if you want to add values in a specific format (as an array for example)
 */
return [
    'Ignore'        => false,           // Used internally to ignore the host, despite a possible match
    'Label'         => false,           // Label for the profile
    'Badge'         => 'Badge Text',
    'ParentProfile' => 'Dynamic Profile Parent Name',
    'CustomCommand' => 'Custom Command',
    'Command'       => function($array, $line) {
        $command = $line->firstValue;
        if ($command) {
            if (!array_key_exists('Custom Command', $array)) {
                $array['Custom Command'] = 'Yes';
            }
            $array['Command'] = $command;
        }
        return $array;
    },
    'Tags'          => function($array, $tags) {
        if ($tags && \count($tags->value)) {
            $array['Tags'] = $tags->value;
        }
        return $array;
    },
    'Trigger' => function($array, $line) {
        $trigger = json_decode($line->valueString, true);
        if ($trigger) {
            if (!array_key_exists('Triggers', $array)) {
                $array['Triggers'] = [];
            }
            $array['Triggers'][] = $trigger;
        }
        return $array;
    }
];