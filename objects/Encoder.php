<?php


class Encoder extends Object {

    protected $id, $siteURL, $streamer_id, $description, $created, $modified, $name;

    protected static function getSearchFieldsNames() {
        return array('siteURL');
    }

    protected static function getTableName() {
        return 'encoders';
    }

    private static function get($siteURL) {
        global $global;
        $sql = "SELECT * FROM  " . static::getTableName() . " WHERE lower(siteURL) = lower('{$siteURL}') LIMIT 1";
        //echo $sql;exit;
        $res = $global['mysqli']->query($sql);
        if ($res) {
            return $res->fetch_assoc();
        } else {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return false;
    }

    private static function getFirst() {
        global $global;
        $sql = "SELECT * FROM  " . static::getTableName() . " LIMIT 1";

        $res = $global['mysqli']->query($sql);
        if ($res) {
            return $res->fetch_assoc();
        } else {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return false;
    }

    static function getFirstURL() {
        $row = static::getFirst();
        return $row['siteURL'];
    }
    
    function getName() {
        return $this->name;
    }

    function setName($name) {
        $this->name = $name;
    }

    function getId() {
        return $this->id;
    }

    function getSiteURL() {
        return $this->siteURL;
    }

    function getCreated() {
        return $this->created;
    }

    function getModified() {
        return $this->modified;
    }

    function setId($id) {
        $this->id = $id;
    }

    function setSiteURL($siteURL) {
        if (!empty($siteURL) && substr($siteURL, -1) !== '/') {
            $siteURL .= "/";
        }
        $this->siteURL = $siteURL;
    }

    function setCreated($created) {
        $this->created = $created;
    }

    function setModified($modified) {
        $this->modified = $modified;
    }
    
    function getStreamer_id() {
        return $this->streamer_id;
    }

    function setStreamer_id($streamer_id) {
        $this->streamer_id = $streamer_id;
    }

    function getDescription() {
        return $this->description;
    }

    function setDescription($description) {
        $this->description = $description;
    }



}
