# SSH to iTerm2

Convert host entries from your [SSH config file](https://www.ssh.com/ssh/config/) into iTerm2 [Dynamic Profiles](https://www.iterm2.com/documentation-dynamic-profiles.html).

Basically: hosts configured in your ssh config files are automatically turned into iTerm2 profiles with a custom ssh command.
These profiles allow for quick connections to your servers using the Profiles manager.

## Whut?

From a practical point of view, having all your hosts configured in ssh config files is *so* easy. You can just `ssh <host>` in 
any local terminal and you're in business. So that's what I like to do. A lot of shells also support auto completion based on the configs.
Unfortunately, it not always easy to remember the correct names for hosts, as I have a number extensive config files for several of my clients.

So I want a list of those hosts, but ideally they should be based on config files.
Most apps out there allow you to configure some kind of favorites/connections/profiles, but it's always manual.

I've always been a big iTerm2 fan, tried most of the alternatives but always ended up coming back.
[Royal TSX](https://www.royalapplications.com/ts/mac/features) came close, but again my favorites and my ssh configs digressed too much to my liking.
 
So finally ended up with "why can't I just use my SSH hosts as favorites?".
Turns out iTerm2 has something called *Dynamic Profiles*. It is continuously monitoring a folder on the system (`~/Library/Application Support/iTerm2/DynamicProfiles`) and
if a usable file is written in there (see the documentation for more info) it is parsed and results in profiles in the app.

So of course I wanted to fill this up based on my ssh config. Looking around I found that I [wasn't the first](https://github.com/derimagia/sshconfig2iterm) with this idea.
Unfortunately that solution left me wanting more. So I decided to write my own using the ideas of sshconfig2iterm as a starting point.   

## Setting up

The only setup this needs is in your ssh config files. 
Any additional information for iTerm is added via comments so that your files remain 100% readable for SSH.
If the comment contains a recognised keyword, it will be used in the profile configuration.

An example `Host` entry:

```bash
# Jump Station to DMZ
Host bastion jumpstation
    # Label Bastion
    # Badge Bastion
    # Tags client.com Production DMZ Jumpstations "tag with spaces"
    # ParentProfile production
    Hostname bastion.client.com
    User me
    IdentityFile ~/.ssh/client.com/bastion.client.com
```

With this in your configs, you can do a couple things: you can ssh to `bastion` or `jumpstation` and it will automatically use the correct hostname, user and identity file.

Notice the comments below the `Host` entry (Anything before the `Host` line is assigned to the previous host or thrown out if it's the first). The syntax is the same as your regular ssh keywords:
either you can use a space to separate the keyword and its value, or you can use an equal sign (So `# Label=Bastion` also works).

Run the tool and get the relevant JSON in the correct folder. That's it! 

If you run the tool with `-s` (or `--save`) it will automatically create a `ssh-config.json` file in the correct folder. 
A few seconds later you'll see a new profile in iTerm2. 
Without the save option a json would be written to the screen. Then it is up to you to make sure it ends up in the profile folder.

As an example, the generated profile could look like this:

```json
{
    "Name": "Bastion",
    "Guid": "a922be92cfab145f6613fa6064a6042d4dae95c5",
    "Badge Text": "Bastion",
    "Dynamic Profile Parent Name": "production",
    "Tags": [
        "client.com",
        "Production",
        "DMZ",
        "Jumpstations",
        "tag with spaces"
    ],
    "Custom Command": "Yes",
    "Command": "ssh bastion"
}
```

### Include

Include statements (`Include ~/.ssh/client.com/config`) **are supported**, all includes will be resolved before any parsing begins.

## Config Keywords

Currently the tool only supports a limited amount of config keywords/profile options, but more can be added if needed (see [src/DynamicProfiles/options.php](src/DynamicProfiles/options.php)).

## Guid

At this point this is an auto-generated SHA1 checksum off the host patterns and cannot be overridden. 
As long as you don't change the patterns the resulting `Guid` remains the same. 

### Ignore

If this is found in a `Host` that would otherwise be included (eg. `# Ignore true` - value doesn't matter), skip the host.

### Label

The label this profile should have in iTerms' profile list.

### Badge

If given a value, this text will be shown as [a badge](https://www.iterm2.com/features.html#badges) in the top right of your terminal.

### ParentProfile

Results in a "*Dynamic Profile Parent Name*"-entry (See the "*Parent Profiles*" section on the [Dynamic Profiles](https://www.iterm2.com/documentation-dynamic-profiles.html)-page for all info)

In short: Since a dynamic profile is not expected to specify all options (the minimal profile is just a `Name` and `Guid`), 
iTerm needs a way to determine values for whatever was omitted. Unless told otherwise it uses the profile marked as default.

I personally have a number of "reference profiles" like `production` (red background, red tab), `staging` (orange background, orange tab) etc that I refer to with this option.
This allows me to base production hosts on the `production` profile, clearly visualizing to me that I should be even more careful than usual :-) 

One of the massive advantages is that it will copy all values whenever the dynamic profiles are reloaded. 
This means that you can just change colors or any other setting in the parent profile. 
All "derived" profiles will be updated automatically.

With regular profiles this is impossible. As soon as you duplicate it it is no longer linked to the original and you'll have to update them individually. 

### CustomCommand

"`Yes`" to configure a custom command to execute via the `Command` keyword. If no `CustomCommand` option is found, an `ssh <first-host-pattern>` custom command will be automatically added for you.

### Command

See above, the actual command to execute if you want to specify your own. (`CustomCommand` is not automatically added at this point, so make sure to add it yourself)

### Tags

A space-separated list of tags for the profile. Spaces within tags are supported by double-quoting them ("*tag with spaces*").

## Command Line Arguments

An overview of the command line arguments. The default behavior is to use `~/.ssh/config` and write the resulting json will to the screen.

### --config (-c)

Specify an alternate ssh config file to use. By default `~/.ssh/config` is used.

### --save (-s)

If this option is specified, the result will be written to a file instead of on the screen. If no value was given, the filename used is `ssh-config.json`.
This option works together with `--directory`

### --directory (-d)

Override the folder to which the resulting file will be written. 
iTerm2's default watched folder is `~/Library/Application Support/iTerm2/DynamicProfiles`

## --wildcard (-w)

Process `Host` entries that contain wildcard patterns instead of skipping them. 
As they usually result in an unusable profile (`ssh server*` won't really work), skipping them is the most logical and thus default behavior. 

## --multi-pattern (-m)

What to do with Host entries that have multiple patterns (`Host server1 server1alternative`)?
 
The default behavior (`first`) is to generate a profile for the first pattern only.
Alternate values are `ignore` (completely ignore the entry) or `all` to generate a separate profile for each of the patterns.   

## --uncommented (-u)

Include `Hosts` that do not have any of the known keywords in their comments instead of ignoring them. This allows you to 
edit your config files in pieces and will only yields profiles for the Hosts you actually updated.  

## --bind (-b)

Add a so called `Bound Hosts` list that includes all patterns and (if present) the `HostName`. This assists you in using iTerms' [Automatic Profile Switching](https://www.iterm2.com/features.html#automatic-profile-switching). 
You'll find the configuration entries on the `Advanced` page of the Profile.

## Various

Tildes (`~`) in the file references are supported if your PHP installation has posix support (should be the case unless you explicitly disabled it).

As already mentioned: `Include` statements are recognised and the files referred to are also processed.