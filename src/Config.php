<?php
namespace OregonCatholicPress\Feature;

/**
 * A feature that can be enabled, disabled, ramped up, and A/B tested,
 * as well as enabled for certain classes of users. These objects
 * should not be accessed directly but rather through the API provided
 * by Feature.php which is more convenient and provides some caching.
 */
class Config
{
    const DEBUG = false;
    const INDENT = 4;
    const UNINDENT = -4;

    /* Keys used in a feature configuration. */
    const DESCRIPTION         = 'description';
    const ENABLED             = 'enabled';
    const USERS               = 'users';
    const GROUPS              = 'groups';
    const ADMIN               = 'admin';
    const INTERNAL            = 'internal';
    const PUBLIC_URL_OVERRIDE = 'public_url_override';
    const BUCKETING           = 'bucketing';

    /* Special values for enabled property. */
    const ON  = 'on';  /* Feature is fully enabled. */
    const OFF = 'off'; /* Feature is fully disabled. */

    /* Bucketing schemes. */
    const UAID   = 'uaid';
    const USER   = 'user';
    const RANDOM = 'random';

    private $name;
    private $cache;
    private $world;

    private $description;
    private $enabled;
    private $users;
    private $groups;
    private $adminVariant;
    private $internalVariant;
    private $publicUrlOverride;
    private $bucketing;

    private $percentages;

    /*
     * Construct a Config object from its config stanza.
     */
    public function __construct($name, $stanza, $world)
    {
        $this->name  = $name;
        $this->cache = array();
        $this->world = $world;

        // Special case to save some memory--if the value is just a
        // string that is the same as setting enabled to that variant
        // (typically 'on' or 'off' but possibly another variant
        // name). This reduces the number of array objects we have to
        // create when reading the config file.
        if (is_null($stanza)) {
            $stanza = array(self::ENABLED => self::OFF);
        } elseif (is_string($stanza)) {
            $stanza = array(self::ENABLED => $stanza);
        }

        // Pull stuff from the config stanza.
        $this->description       = $this->parseDescription($stanza);
        $this->enabled           = $this->parseEnabled($stanza);
        $this->users             = $this->parseUsersOrGroups($stanza, self::USERS);
        $this->groups            = $this->parseUsersOrGroups($stanza, self::GROUPS);
        $this->adminVariant      = $this->parseVariantName($stanza, self::ADMIN);
        $this->internalVariant   = $this->parseVariantName($stanza, self::INTERNAL);
        $this->publicUrlOverride = $this->parsePublicURLOverride($stanza);
        $this->bucketing         = $this->parseBucketBy($stanza);

        // Put the enabled value into a more useful form for actually doing bucketing.
        $this->percentages = $this->computePercentages();
    }

    ////////////////////////////////////////////////////////////////////////
    // Public API, though note that Feature.php is the only code that
    // should be using this class directly.

    /*
     * Is this feature enabled for the default id and the logged in
     * user, if any?
     */
    public function isEnabled()
    {
        if (self::DEBUG) {
            $this->dbg('Config::isEnabled()', self::INDENT);
        }
        $bucketingID = $this->bucketingID();
        if (self::DEBUG) {
            $this->dbg('- $bucketingID: '.$bucketingID);
        }
        $userID = $this->world->userID();
        if (self::DEBUG) {
            $this->dbg('- $userID: '.$userID);
        }
        $variant = $this->chooseVariant($bucketingID, $userID, false) !== self::OFF;
        if (self::DEBUG) {
            $this->dbg('- $variant: '.$variant);
            $this->dbg(null, self::UNINDENT);
        }
        return $variant;
    }

    /*
     * What variant is enabled for the default id and the logged in
     * user, if any?
     */
    public function variant()
    {
        $bucketingID = $this->bucketingID();
        $userID      = $this->world->userID();
        return $this->chooseVariant($bucketingID, $userID, true);
    }

    /*
     * Is this feature enabled for the given user?
     */
    public function isEnabledFor($user)
    {
        $userID = $this->getUserIdFrom($user);
        return $this->chooseVariant($userID, $userID, false) !== self::OFF;
    }

    /*
     * Is this feature enabled, bucketing on the given bucketing
     * ID? (Other methods of enabling a feature and specifying a
     * variant such as users, groups, and query parameters, will still
     * work.)
     */
    public function isEnabledBucketingBy($bucketingID)
    {
        $userID = $this->world->userID();
        return $this->chooseVariant($bucketingID, $userID, false) !== self::OFF;
    }

    /*
     * What variant is enabled for the given user?
     */
    public function variantFor($user)
    {
        $userID = $this->getUserIdFrom($user);
        return $this->chooseVariant($userID, $userID, true);
    }

    /*
     * What variant is enabled, bucketing on the given bucketing ID,
     * if any?
     */
    public function variantBucketingBy($bucketingID)
    {
        $userID = $this->world->userID();
        return $this->chooseVariant($bucketingID, $userID, true);
        ;
    }

    /*
     * Description of the feature.
     */
    public function description()
    {
        return $this->description;
    }


    ////////////////////////////////////////////////////////////////////////
    // Internals

    /*
     * Accept different user objects and return user_id
     */
    private function getUserIdFrom($user)
    {
        if ($user instanceof REST_User) {
            // $user->user_id is protected so not accessible
            return $user->getUserId();
        }
        return $user->user_id;
    }

    /*
     * Get the name of the variant we should use. Returns OFF if the
     * feature is not enabled for $identifier. When $inVariantMethod is
     * true will also check the conditions that should hold for a
     * correct call to variant or variantFor: they should not be
     * called for features that are completely enabled (i.e. 'enabled'
     * => 'on') since all such variant-specific code should have been
     * cleaned up before changing the config and they should not be
     * called if the feature is, in fact, disabled for the given id
     * since those two methods should always be guarded by an
     * isEnabled/isEnabledFor call.
     *
     * @param $bucketingID the id used to assign a variant based on
     * the percentage of users that should see different variants.
     *
     * @param $userID the identity of the user to be used for the
     * special 'admin', 'users', and 'groups' access checks.
     *
     * @param $inVariantMethod were we called from variant or
     * variantFor, in which case we want to perform some certain
     * sanity checks to make sure the code is being used correctly.
     */
    private function chooseVariant($bucketingID, $userID, $inVariantMethod)
    {
        if (self::DEBUG) {
            $this->dbg('Config::chooseVariant('.$bucketingID.','.$userID.','.$inVariantMethod.')', self::INDENT);
        }
        if ($inVariantMethod && $this->enabled === self::ON) {
            $this->error("Variant check when fully enabled");
        }

        if (is_string($this->enabled)) {
            // When enabled is on, off, or a variant name, that's the
            // end of the story.
            if (self::DEBUG) {
                $this->dbg('- is_string($this->enabled)');
                $this->dbg('- $this->enabled: '.$this->enabled);
                $this->dbg(null, self::UNINDENT);
            }
            return $this->enabled;
        } else {
            if (self::DEBUG) {
                $this->dbg('- else');
            }
            if (is_null($bucketingID)) {
                throw new InvalidArgumentException(
                    "no bucketing ID supplied. if testing, configure feature " .
                    "with enabled => 'on' or 'off', feature name = " .
                    $this->name
                );
            }

            $bucketingID = (string)$bucketingID;
            if (self::DEBUG) {
                $this->dbg('  - $bucketingID: '.$bucketingID);
            }
            if (array_key_exists($bucketingID, $this->cache)) {
                if (self::DEBUG) {
                    $this->dbg(
                        '    - array_key_exists($bucketingID, $this->cache)'. array_key_exists(
                            $bucketingID,
                            $this->cache
                        )
                    );
                }
                // Note that this caching is not just an optimization:
                // it prevents us from double logging a single
                // feature--we only want to log each distinct checked
                // feature once.
                //
                // The caching also affects the semantics when we use
                // random bucketing (rather than hashing the id), i.e.
                // 'random' => 'true', by making the variant and
                // enabled status stable within a request.
                return $this->cache[$bucketingID];
            } else {
                if (self::DEBUG) {
                    $this->dbg('    - else');
                }
                list($variant, $selector) =
                    $this->variantFromURL($userID) ?:
                    $this->variantForUser($userID) ?:
                    $this->variantForGroup($userID) ?:
                    $this->variantForAdmin($userID) ?:
                    $this->variantForInternal() ?:
                    $this->variantByPercentage($bucketingID) ?:
                    array(self::OFF, 'w');
                if (self::DEBUG) {
                    $this->dbg('    - $variant: '.$variant);
                    $this->dbg('    - $selector: '.$selector);
                }
                if ($inVariantMethod && $variant === self::OFF) {
                    $this->error("Variant check outside enabled check");
                }

                $this->world->log($this->name, $variant, $selector);
                if (self::DEBUG) {
                    $this->dbg('    - $this->cache[$bucketingID] = $variant: '.$variant);
                    $this->dbg(null, self::UNINDENT);
                }
                return $this->cache[$bucketingID] = $variant;
            }
        }
    }

    /*
     * Return the globally accessible ID used by the one-arg isEnabled
     * and variant methods based on the feature's bucketing property.
     */
    private function bucketingID()
    {
        if (self::DEBUG) {
            $this->dbg('Config::bucketingID()', self::INDENT);
        }
        switch ($this->bucketing) {
            case self::UAID:
            case self::RANDOM:
                // In the RANDOM case we still need a bucketing id to keep
                // the assignment stable within a request.
                // Note that when being run from outside of a web request (e.g. crons),
                // there is no UAID, so we default to a static string
                $uaid = $this->world->uaid();
// var_dump($this->world);
                if (self::DEBUG) {
                    $this->dbg('- self::UAID/self::RANDOM');
                }
                if (self::DEBUG) {
                    $this->dbg(null, self::UNINDENT);
                }
                return $uaid ? $uaid : "no uaid";
            case self::USER:
// var_dump($this->world);
                $userID = $this->world->userID();
                // Not clear if this is right. There's an argument to be
                // made that if we're bucketing by userID and the user is
                // not logged in we should treat the feature as disabled.
                return !is_null($userID) ? $userID : $this->world->uaid();
            default:
                throw new InvalidArgumentException("Bad bucketing: $this->bucketing");
        }
    }

    /*
     * For internal requests or if the feature has public_url_override
     * set to true, a specific variant can be specified in the
     * 'features' query parameter. In all other cases return false,
     * meaning nothing was specified. Note that foo:off will turn off
     * the 'foo' feature.
     */
    private function variantFromURL($userID)
    {
        if ($this->publicUrlOverride or
            $this->world->isInternalRequest() or
            $this->world->isAdmin($userID)
        ) {
            $urlFeatures = $this->world->urlFeatures();
            if ($urlFeatures) {
                foreach (explode(',', $urlFeatures) as $f) {
                    $parts = explode(':', $f);
                    if ($parts[0] === $this->name) {
                        return array(isset($parts[1]) ? $parts[1] : self::ON, 'o');
                    }
                }
            }
        }
        return false;
    }

    /*
     * Get the variant this user should see, if one was configured,
     * false otherwise.
     */
    private function variantForUser($userID)
    {
        if ($this->users) {
            $name = $this->world->userName($userID);
            if ($name && array_key_exists($name, $this->users)) {
                return array($this->users[$name], 'u');
            }
        }
        return false;
    }

    /*
     * Get the variant this user should see based on their group
     * memberships, if one was configured, false otherwise. N.B. If
     * the user is in multiple groups that are configured to see
     * different variants, they'll get the variant for one of their
     * groups but there's no saying which one. If this is a problem in
     * practice we could make the configuration more complex. Or you
     * can just provide a specific variant via the 'users' property.
     */
    private function variantForGroup($userID)
    {
        if ($userID) {
            foreach ($this->groups as $groupID => $variant) {
                if ($this->world->inGroup($userID, $groupID)) {
                    return array($variant, 'g');
                }
            }
        }
        return false;
    }

    /*
     * What variant, if any, should we return if the current user is
     * an admin.
     */
    private function variantForAdmin($userID)
    {
        if (self::DEBUG) {
            $this->dbg('Config::variantForAdmin('.$userID.')', self::INDENT);
        }
        if ($userID && $this->adminVariant) {
            if (self::DEBUG) {
                $this->dbg(' - $userID && $this->adminVariant: '.($userID && $this->adminVariant));
            }
            if ($this->world->isAdmin($userID)) {
                if (self::DEBUG) {
                    $this->dbg('    - $this->world->isAdmin($userID):');
                    $this->dbg('    - ['.$this->adminVariant.', "a"]', self::UNINDENT);
                }
                return array($this->adminVariant, 'a');
            }
        }
        return false;
    }

    /*
     * What variant, if any, should we return for internal requests.
     */
    private function variantForInternal()
    {
        if ($this->internalVariant) {
            if ($this->world->isInternalRequest()) {
                return array($this->internalVariant, 'i');
            }
        }
        return false;
    }

    /*
     * Finally, the normal case: use the percentage of users who
     * should see each variant to map a randomish number to a
     * particular variant.
     */
    private function variantByPercentage($identifier)
    {
        $number = 100 * $this->randomish($identifier);
        foreach ($this->percentages as $value) {
            // === 100 check may not be necessary but I'm not good
            // enough numerical analyst to be sure.
            if ($number < $value[0] || $value[0] === 100) {
                return array($value[1], 'w');
            }
        }
        return false;
    }

    /*
     * A randomish number in [0, 1) based on the feature name and $identifier
     * unless we are bucketing completely at random.
     */
    private function randomish($identifier)
    {
        return $this->bucketing === self::RANDOM
            ? $this->world->random()
            : $this->world->hash($this->name . '-' . $identifier);
    }

    ////////////////////////////////////////////////////////////////////////
    // Configuration parsing

    private function parseDescription($stanza)
    {
        return Util::arrayGet($stanza, self::DESCRIPTION, 'No description.');
    }

    /*
     * Parse the 'enabled' property of the feature's config stanza.
     */
    private function parseEnabled($stanza)
    {

        $enabled = Util::arrayGet($stanza, self::ENABLED, 0);

        if (is_numeric($enabled)) {
            if ($enabled < 0) {
                $this->error("enabled ($enabled) < 0");
                $enabled = 0;
            } elseif ($enabled > 100) {
                $this->error("enabled ($enabled) > 100");
                $enabled = 100;
            }
            return array('on' => $enabled);

        } elseif (is_string($enabled) or is_array($enabled)) {
            return $enabled;
        } else {
            $this->error("Malformed enabled property");
        }
    }

    /*
     * Returns an array of pairs with the first element of the pair
     * being the upper-boundary of the variants percentage and the
     * second element being the name of the variant.
     */
    private function computePercentages()
    {
        $total = 0;
        $percentages = array();
        if (is_array($this->enabled)) {
            foreach ($this->enabled as $variant => $percentage) {
                if (!is_numeric($percentage) || $percentage < 0 || $percentage > 100) {
                    $this->error("Bad percentage $percentage");
                }
                if ($percentage > 0) {
                    $total += $percentage;
                    $percentages[] = array($total, $variant);
                }
                if ($total > 100) {
                    $this->error("Total of percentages > 100: $total");
                }
            }
        }
        return $percentages;
    }

    /*
     * Parse the value of the 'users' and 'groups' properties of the
     * feature's config stanza, returning an array mappinng the user
     * or group names to they variant they should see.
     */
    private function parseUsersOrGroups($stanza, $what)
    {
        $value = Util::arrayGet($stanza, $what);
        if (is_string($value) || is_numeric($value)) {
            // Users are configrued with their user names. Groups as
            // numeric ids. (Not sure if that's a great idea.)
            return array($value => self::ON);

        } elseif (self::isList($value)) {
            $result = array();
            foreach ($value as $who) {
                $result[strtolower($who)] = self::ON;
            }
            return $result;

        } elseif (is_array($value)) {
            $result = array();
            $badKeys = is_array($this->enabled) ?
                array_keys(array_diff_key($value, $this->enabled)) :
                array();
            if (!$badKeys) {
                foreach ($value as $variant => $whos) {
                    foreach (self::asArray($whos) as $who) {
                        $result[strtolower($who)] = $variant;
                    }
                }
                return $result;

            } else {
                $this->error("Unknown variants " . implode(', ', $badKeys));
            }
        } else {
            return array();
        }
    }

    /*
     * Parse the variant name value for the 'admin' and 'internal'
     * properties. If non-falsy, must be one of the keys in the
     * enabled map unless enabled is 'on' or 'off'.
     */
    private function parseVariantName($stanza, $what)
    {
        $value = Util::arrayGet($stanza, $what);
        if ($value) {
            if (is_array($this->enabled)) {
                if (array_key_exists($value, $this->enabled)) {
                    return $value;
                } else {
                    $this->error("Unknown variant $value");
                }
            } else {
                return $value;
            }
        } else {
            return false;
        }
    }

    private function parsePublicURLOverride($stanza)
    {
        return Util::arrayGet($stanza, self::PUBLIC_URL_OVERRIDE, false);
    }

    private function parseBucketBy($stanza)
    {
        return Util::arrayGet($stanza, self::BUCKETING, self::UAID);
    }

    ////////////////////////////////////////////////////////////////////////
    // Genericish utilities

    /*
     * Is the given object an array value that could have been created
     * with array(...) with no =>'s in the ...?
     */
    private static function isList($var)
    {
        return is_array($var) and array_keys($var) === range(0, count($var) - 1);
    }

    private static function asArray($var)
    {
        return is_array($var) ? $var : array($var);
    }

    private function error($message)
    {
        $message;
        // IMPLEMENT FOR YOUR CONTEXT
    }

    public function dbg($msg, $indent = 0)
    {
        static $first = true;
        static $pad = self::UNINDENT;
        if ($first) {
            echo "\n";
            $first = false;
        }
        if ($indent) {
            $pad += $indent;
        }
        if ($msg) {
            echo str_repeat(' ', $pad).$msg."\n";
        }
    }
}
