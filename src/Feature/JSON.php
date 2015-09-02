<?php
namespace OregonCatholicPress\Feature;

/*
 * Utility for turning configs into JSON-encodeable data.
 */
class JSON
{

    /*
     * Return the given config stanza as an array that can be json
     * encoded in a form that is slightly easier to deal with in
     * Javascript.
     */
    public static function stanza($key, $serverConfig = null)
    {
        $stanza = self::findStanza($key, $serverConfig);
        return $stanza !== false ? self::translate($key, $stanza) : false;
    }

    private static function findStanza($key, $cursor)
    {
        $step = strtok($key, '.');
        while ($step) {
            if (is_array($cursor) && array_key_exists($step, $cursor)) {
                $cursor = $cursor[$step];
            } else {
                return false;
            }
            $step = strtok('.');
        }
        return $cursor;
    }

    private static function translate($key, $value)
    {

        $spec = self::makeSpec($key);

        $internalUrl = true;

        if (is_numeric($value)) {
            $value = array('enabled' => (int)$value);
        } elseif (is_string($value)) {
            $value = array('enabled' => $value);
        }

        $enabled = Util::arrayGet($value, 'enabled', 0);
        $users   = self::expandUsersOrGroups(Util::arrayGet($value, 'users', array()));
        $groups  = self::expandUsersOrGroups(Util::arrayGet($value, 'groups', array()));

        if ($enabled === 'off') {
            $spec['variants'][] = self::makeVariantWithUsersAndGroups('on', 0, $users, $groups);
            $internalUrl = false;
        } elseif (is_numeric($enabled)) {
            $spec['variants'][] = self::makeVariantWithUsersAndGroups('on', (int)$enabled, $users, $groups);
        } elseif (is_string($enabled)) {
            $spec['variants'][] = self::makeVariantWithUsersAndGroups($enabled, 100, $users, $groups);
            $internalUrl = false;
        } elseif (is_array($enabled)) {
            foreach ($enabled as $version => $amount) {
                if (is_numeric($amount)) {
                    // Kind of a kludge. $amount had better be numeric and
                    // there have been configs deployed where it
                    // wasn't which breaks the Catapult config history
                    // scripts. This will just skip those.
                    $spec['variants'][] = self::makeVariantWithUsersAndGroups($version, $amount, $users, $groups);
                }
            }
        }
        $spec['internalUrl_override'] = $internalUrl;

        if (array_key_exists('admin', $value)) {
            $spec['admin'] = $value['admin'];
        }
        if (array_key_exists('internal', $value)) {
            $spec['internal'] = $value['internal'];
        }
        if (array_key_exists('bucketing', $value)) {
            $spec['bucketing'] = $value['bucketing'];
        }
        if (array_key_exists('internal', $value)) {
            $spec['internal'] = $value['internal'];
        }
        if (array_key_exists('public_url_override', $value)) {
            $spec['public_url_override'] = $value['public_url_override'];
        }

        return $spec;
    }

    private static function makeSpec($key)
    {
        return array(
            'key' => $key,
            'internalUrl_override' => false,
            'public_url_override' => false,
            'bucketing' => 'uaid',
            'admin' => null,
            'internal' => null,
            'variants' => array());
    }

/*
    private static function makeVariant ($name, $percentage) {
        return array(
            'name' => $name,
            'percentage' => $percentage,
            'users' => array(),
            'groups' => array());
    }
*/

    private static function makeVariantWithUsersAndGroups($name, $percentage, $users, $groups)
    {
        return array(
            'name'       => $name,
            'percentage' => $percentage,
            'users'      => self::extractForVariant($users, $name),
            'groups'     => self::extractForVariant($groups, $name),
        );
    }

    private static function extractForVariant($usersOrGroups, $name)
    {
        $result = array();
        foreach ($usersOrGroups as $thing => $variant) {
            if ($variant == $name) {
                $result[] = $thing;
            }
        }
        return $result;
    }

    // This is based on parseUsersOrGroups in Config. Probably
    // this logic should be put in that class in a form that we can
    // use.
    private static function expandUsersOrGroups($value)
    {
        if (is_string($value) || is_numeric($value)) {
            return array($value => Config::ON);

        } elseif (self::isList($value)) {
            $result = array();
            foreach ($value as $who) {
                $result[$who] = Config::ON;
            }
            return $result;

        } elseif (is_array($value)) {
            $result = array();
            foreach ($value as $variant => $whos) {
                foreach (self::asArray($whos) as $who) {
                    $result[$who] = $variant;
                }
            }
            return $result;

        } else {
            return array();
        }
    }

    private static function isList($var)
    {
        return is_array($var) and array_keys($var) === range(0, count($var) - 1);
    }

    private static function asArray($var)
    {
        return is_array($var) ? $var : array($var);
    }
}
