<?php

define('PROCESSWIRE', true);


class RoboFile extends \Robo\Tasks {
    var $host = "host.com";
    var $ssh_user = "user";
    var $remote_path = "/home/user/web/host.com/public_html";

    function __construct() {
        global $config;
        $config = new stdClass();
        if (file_exists('./config.php')) {
            require './config.php';
        } else {
            echo "config.php was not found.\n";
            die();
        }
    }

    // REMOTE

    function remotePush($post = true) {
        $this->dbDump();
        $this->filesSyncToRemote();
        if ($post && $post !== 'false')
            $this->filesSyncToRemotePost();
    }


    function remotePull() {
        $this->taskSshExec($this->host, $this->ssh_user)
            ->remoteDir($this->remote_path . '/site')
            ->exec('composer install')
            ->exec('php vendor/bin/robo db:dump')
            ->run();
        $this->filesSyncFromRemote();
        $this->dbImport();
    }

    // DATABASE

    function dbDump() {
        global $config;
        $this->_exec("mkdir -p ./database");
        $this->_exec("mysqldump -u $config->dbUser -p$config->dbPass $config->dbName > ./database/database.sql");
    }


    function dbImport() {
        global $config;
        if (file_exists('database/database.sql')) {
            $this->_exec("mysql -u $config->dbUser -p$config->dbPass $config->dbName < ./database/database.sql");
        } else {
            echo "No database file found.";
        }
    }

    // FILES

    function filesSyncToRemote() {
        $this->taskRsync()
            ->fromPath('../')
            ->toHost($this->host)
            ->toUser($this->ssh_user)
            ->toPath($this->remote_path)
            ->delete()
            ->recursive()
            ->excludeFrom('../.gitignore')
            ->excludeVcs()
            ->checksum()
            ->wholeFile()
            ->progress()
            ->humanReadable()
            ->stats()
            ->run();

        // push extra files
        $this->taskRsync()
            ->fromPath('../')
            ->toHost($this->host)
            ->toUser($this->ssh_user)
            ->toPath($this->remote_path)
            ->recursive()
            ->delete()
            ->excludeVcs()
            ->checksum()
            ->wholeFile()
            ->progress()
            ->humanReadable()
            ->stats()
            ->filesFrom('../.rsync-include')
            ->run();
    }

    function filesSyncToRemotePost() {
        $this->taskSshExec($this->host, $this->ssh_user)
            ->remoteDir($this->remote_path . '/site')
            ->exec('composer install')
            ->exec('php vendor/bin/robo db:import')
            ->run();
    }

    function filesSyncFromRemote() {
        $this->taskRsync()
            ->fromHost($this->host)
            ->fromUser($this->ssh_user)
            ->fromPath($this->remote_path . '/')
            ->toPath('./')
            ->recursive()
            ->excludeFrom('../.gitignore')
            ->excludeVcs()
            ->checksum()
            ->wholeFile()
            ->progress()
            ->humanReadable()
            ->stats()
            ->run();
    }

}