<?php


class Streamer extends ObjectYPT{

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
        $statement = $global['mysqli']->prepare('SELECT * FROM ' . static::getTableName() . ' WHERE user = ? AND lower(siteURL) = lower(?) LIMIT 1');
        $statement->bind_param('ss', $user, $siteURL);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        return $row;
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
        return empty($row['siteURL']) ? '' : addLastSlash($row['siteURL']);
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

    private function updatePassFieldIfNeed(){
        global $global, $mysqlDatabase;
        $sql = "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = '{$mysqlDatabase}' AND TABLE_NAME = 'streamers' AND COLUMN_NAME = 'pass'";

        $res = $global['mysqli']->query($sql);
        $row = ($res) ? $res->fetch_assoc() : false;
        if(!empty($row)){
            if($row['CHARACTER_MAXIMUM_LENGTH'] < 250){
                $sql2 = 'ALTER TABLE `streamers` 
                CHANGE COLUMN `pass` `pass` VARCHAR(255) NOT NULL;';

                $insert_row = $global['mysqli']->query($sql2);
            }
        }
    }

    function save(){
        global $global;
        $this->updatePassFieldIfNeed();
        if (!empty($this->id)) {
            $statement = $global['mysqli']->prepare('UPDATE streamers SET siteURL=?, user=?, pass=?, modified=NOW() WHERE id=?');
            $statement->bind_param('sssi', $this->siteURL, $this->user, $this->pass, $this->id);
        } else {
            $statement = $global['mysqli']->prepare('INSERT INTO streamers (siteURL,user,pass,created,modified) VALUES (?,?,?,NOW(),NOW())');
            $statement->bind_param('sss', $this->siteURL, $this->user, $this->pass);
        }
        $statement->execute();
        $id = $this->id ?: $global['mysqli']->insert_id;
        $statement->close();
        return $id;
    }

}
