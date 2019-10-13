<?php

class Encoder extends ObjectYPT
{

    protected $id, $siteURL, $streamers_id, $description, $created, $modified, $name;

    /**
     * @return array An array of the field names used when performing searches.
     */
    protected static function getSearchFieldsNames()
    {
        return array('siteURL');
    }

    /**
     * @return string The name of the table used for when building SQL queries.
     */
    protected static function getTableName()
    {
        return 'encoders';
    }

    /**
     * @param string $siteURL The site URL.
     * @return array Fetches a row from the database.
     */
    private static function get($siteURL)
    {
        global $global;
        $sql = "SELECT * FROM  " . static::getTableName() . " WHERE lower(siteURL) = lower('{$siteURL}') LIMIT 1";
        //echo $sql;exit;
        $res = $global['mysqli']->query($sql);
        if ($res) {
            return $res->fetch_assoc();
        }
        die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
    }

    /**
     * @return array Fetches just the first row from a database table.
     */
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

    /**
     * @return string Fetches the site URL cited by the first row of a database table.
     */
    static function getFirstURL()
    {
        $row = static::getFirst();
        return $row['siteURL'];
    }

    function getName()
    {
        return $this->name;
    }

    function setName($name)
    {
        $this->name = $name;
    }

    function getId()
    {
        return $this->id;
    }

    function getSiteURL()
    {
        return $this->siteURL;
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

    function setCreated($created)
    {
        $this->created = $created;
    }

    function setModified($modified)
    {
        $this->modified = $modified;
    }

    function getStreamers_id()
    {
        return $this->streamers_id;
    }

    function setStreamers_id($streamers_id)
    {
        $this->streamers_id = $streamers_id;
    }

    function getDescription()
    {
        return $this->description;
    }

    function setDescription($description)
    {
        $this->description = $description;
    }

}
