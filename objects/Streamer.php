<?php


class Streamer extends ObjectYPT
{

    protected $id, $siteURL, $user, $pass, $created, $modified;

    protected static function getSearchFieldsNames()
    {
        return array('siteURL');
    }

    protected static function getTableName()
    {
        return 'streamers';
    }

    private static function get($user, $siteURL)
    {
        global $global;
        $sql = "SELECT * FROM  " . static::getTableName() . " WHERE user = '{$user}' AND lower(siteURL) = lower('{$siteURL}') LIMIT 1";
        //echo $sql;exit;
        $res = $global['mysqli']->query($sql);
        if ($res) {
            return $res->fetch_assoc();
        }
        die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
    }

    private static function getFirst()
    {
        global $global;
        $sql = "SELECT * FROM  " . static::getTableName() . " LIMIT 1";

        $res = $global['mysqli']->query($sql);
        if ($res) {
            return $res->fetch_assoc();
        }
        die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
    }

    static function getFirstURL()
    {
        $row = static::getFirst();
        return addLastSlash($row['siteURL']);
    }

    static function createIfNotExists($user, $pass, $siteURL, $encodedPass = false)
    {
        if (!$encodedPass || $encodedPass === 'false') {
            $pass = md5($pass);
        }
        if (substr($siteURL, -1) !== '/') {
            $siteURL .= "/";
        }
        if ($row = static::get($user, $siteURL)) {
            if (!empty($row['id'])) {
                return $row['id'];
            }
        }

        $s = new Streamer('');
        $s->setUser($user);
        $s->setPass($pass);
        $s->setSiteURL($siteURL);
        return $s->save();
    }

    function getId()
    {
        return $this->id;
    }

    function getSiteURL()
    {
        return $this->siteURL;
    }

    function getUser()
    {
        return $this->user;
    }

    function getPass()
    {
        return $this->pass;
    }

    function getCreated()
    {
        return $this->created;
    }

    function getModified()
    {
        return $this->modified;
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function setSiteURL($siteURL)
    {
        if (!empty($siteURL) && substr($siteURL, -1) !== '/') {
            $siteURL .= "/";
        }
        $this->siteURL = $siteURL;
    }

    function setUser($user)
    {
        $this->user = $user;
    }

    function setPass($pass)
    {
        $this->pass = $pass;
    }

    function setCreated($created)
    {
        $this->created = $created;
    }

    function setModified($modified)
    {
        $this->modified = $modified;
    }

}
