<?php
namespace OregonCatholicPress\Feature;

/**
 * Perform some checks on the Feature part of a config file. At the
 * moment performs only true syntax checks: it finds things that are
 * meaningless to the real parsing code and flags them. The only
 * exception is the check for old-style configuration which shows up
 * as an enabled value of 'rampup' accompanied by a 'rampup' clause.
 *
 * Could possibly be extended to detect various violations of tidyness
 * such as having users and groups configured for a config with a
 * string 'enabled' or even 'enabled' => 100.
 */
class Lint
{

    private $checked;
    private $errors;
    private $path;

    public function __construct()
    {
        $this->checked = 0;
        $this->errors  = array();
        $this->path    = array();
        $this->syntax_keys = array(
            Config::ENABLED,
            Config::USERS,
            Config::GROUPS,
            Config::ADMIN,
            Config::INTERNAL,
            Config::PUBLIC_URL_OVERRIDE,
            Config::BUCKETING,
            'data',
        );

        $this->legalBucketingValues = array(
            Config::UAID,
            Config::USER,
            Config::RANDOM,
        );
    }

    public function run($file = null)
    {
        $config = $this->fromFile($file);
        $this->assert($config, "*** Bad configuration.");
        $this->lintNested($config);
    }

    public function checked()
    {
        return $this->checked;
    }

    public function errors()
    {
        return $this->errors;
    }

    private function fromFile($file)
    {
        global $serverConfig;
        $content = file_get_contents($file);
        error_reporting(0);
        $result = eval('?>' . $content);
        error_reporting(-1);
        if ($result === null) {
            return $serverConfig;
        } elseif ($result === false) {
            return false;
        } else {
            Logger::error("Wut? $result");
            return false;
        }
    }

    /*
     * Recursively check nested feature configurations. Skips any keys
     * that have a syntactic meaning which includes 'data'.
     */
    private function lintNested($config)
    {
        foreach ($config as $name => $stanza) {
            if (!in_array($name, $this->syntax_keys)) {
                $this->runLint($name, $stanza);
            }
        }
    }

    private function runLint($name, $stanza)
    {
        array_push($this->path, $name);
        $this->checked += 1;
        if (is_array($stanza)) {
            $this->checkForOldstyle($stanza);
            $this->checkEnabled($stanza);
            $this->checkUsers($stanza);
            $this->checkGroups($stanza);
            $this->checkAdmin($stanza);
            $this->checkInternal($stanza);
            $this->checkPublicURLOverride($stanza);
            $this->checkBucketing($stanza);
            $this->lintNested($stanza);
        } else {
            $this->assert(is_string($stanza), "Bad stanza: $stanza.");
        }
        array_pop($this->path);
    }

    private function assert($okay, $message)
    {
        if (!$okay) {
            $loc = "[" . implode('.', $this->path) . "]";
            array_push($this->errors, "$loc $message");
        }
    }

    private function checkForOldstyle($stanza)
    {
        $enabled = Feature_Util::arrayGet($stanza, Config::ENABLED, 0);
        $rampup  = Feature_Util::arrayGet($stanza, 'rampup', null);
        $this->assert($enabled !== 'rampup' || !$rampup, "Old-style config syntax detected.");
    }

    // 'enabled' must be a string, a number in [0,100], or an array of
    // (string => ints) such that the ints are all in [0,100] and the
    // total is <= 100.
    private function checkEnabled($stanza)
    {
        if (array_key_exists(Config::ENABLED, $stanza)) {
            $enabled = $stanza[Config::ENABLED];
            if (is_numeric($enabled)) {
                $this->assert($enabled >= 0, Config::ENABLED . " too small: $enabled");
                $this->assert($enabled <= 100, Config::ENABLED . "too big: $enabled");
            } elseif (is_array($enabled)) {
                $tot = 0;
                foreach ($enabled as $k => $v) {
                    $this->assert(is_string($k), "Bad key $k in $enabled");
                    $this->assert(is_numeric($v), "Bad value $v for $k in $enabled");
                    $this->assert($v >= 0, "Bad value $v (too small) for $k");
                    $this->assert($v <= 100, "Bad value $v (too big) for $k");
                    if (is_numeric($v)) {
                        $tot += $v;
                    }
                }
                $this->assert($tot >= 0, "Bad total $tot (too small)");
                $this->assert($tot <= 100, "Bad total $tot (too big)");
            }
        }
    }

    private function checkUsers($stanza)
    {
        if (array_key_exists(Config::USERS, $stanza)) {
            $users = $stanza[Config::USERS];
            if (is_array($users) && !self::isList($users)) {
                foreach ($users as $variant => $value) {
                    $this->assert(is_string($variant), "User variant names must be strings.");
                    $this->checkUserValue($value);
                }
            } else {
                $this->checkUserValue($users);
            }
        }
    }

    private function checkUserValue($users)
    {
        $this->assert(
            is_string($users) || self::isList($users),
            Config::USERS . " must be string or list of strings: '$users'"
        );
        if (self::isList($users)) {
            foreach ($users as $user) {
                $this->assert(
                    is_string($user),
                    Config::USERS . " elements must be strings: '$user'"
                );
            }
        }
    }

    private function checkGroups($stanza)
    {
        if (array_key_exists(Config::GROUPS, $stanza)) {
            $groups = $stanza[Config::GROUPS];
            if (is_array($groups) && !self::isList($groups)) {
                foreach ($groups as $variant => $value) {
                    $this->assert(is_string($variant), "Group variant names must be strings.");
                    $this->checkGroupValue($value);
                }
            } else {
                $this->checkGroupValue($groups);
            }
        }
    }

    private function checkGroupValue($groups)
    {
        $this->assert(
            is_numeric($groups) || self::isList($groups),
            Config::GROUPS . " must be number or list of numbers"
        );
        if (self::isList($groups)) {
            foreach ($groups as $group) {
                $this->assert(is_numeric($group), Config::GROUPS . " elements must be numbers: '$group'");
            }
        }
    }


    private function checkAdmin($stanza)
    {
        if (array_key_exists(Config::ADMIN, $stanza)) {
            $admin = $stanza[Config::ADMIN];
            $this->assert(is_string($admin), "Admin must be string naming variant: '$admin'");
        }
    }

    private function checkInternal($stanza)
    {
        if (array_key_exists(Config::INTERNAL, $stanza)) {
            $internal = $stanza[Config::INTERNAL];
            $this->assert(is_string($internal), "Internal must be string naming variant: '$internal'");
        }
    }

    private function checkPublicURLOverride($stanza)
    {
        if (array_key_exists(Config::PUBLIC_URL_OVERRIDE, $stanza)) {
            $publicUrlOverride = $stanza[Config::PUBLIC_URL_OVERRIDE];
            $this->assert(is_bool($publicUrlOverride), "publicUrlOverride must be a boolean: '$publicUrlOverride'");
            if (is_bool($publicUrlOverride)) {
                $this->assert($publicUrlOverride === true, "Gratuitous publicUrlOverride (defaults to false)");
            }
        }
    }

    private function checkBucketing($stanza)
    {
        if (array_key_exists(Config::BUCKETING, $stanza)) {
            $bucketing = $stanza[Config::BUCKETING];
            $this->assert(is_string($bucketing), "Non-string bucketing: '$bucketing'");
            $this->assert(in_array($bucketing, $this->legalBucketingValues), "Illegal bucketing: '$bucketing'");
        }
    }

    private static function isList($var)
    {
        return is_array($var) and array_keys($var) === range(0, count($var) - 1);
    }
}
