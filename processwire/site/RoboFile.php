<?php

// PROCESSWIRE is required for the config file the be read.
define('PROCESSWIRE', true);


class RoboFile extends \Robo\Tasks {

    /**
     * @var string $host            Remote host.
     */
    var $host = "host.com";
    /**
     * @var string $ssh_user        User on remote server, used for SSH login.
     */
    var $ssh_user = "user";
    /**
     * @var string $remote_path     Path on remote server.
     */
    var $remote_path = "/home/user/web/host.com/public_html";

    /**
     *  Require Processwire config so we can read database details.
     */
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

    /**
     * Pushes changes to remote server. By default will also sync database. Use false to disable remote database import.
     *
     * @param bool|true $post       Sync database?
     */
    function remotePush($post = true) {
        $this->dbDump();
        $this->filesSyncToRemote();
        if ($post && $post !== 'false')
            $this->filesSyncToRemotePost();
    }


    /**
     * Pulls changes from remote server. Will also sync database.
     */
    function remotePull() {
        $this->taskSshExec($this->host, $this->ssh_user)
            ->remoteDir($this->remote_path . '/site')
            ->exec('composer install')
            ->exec('php vendor/bin/robo db:dump')
            ->run();
        $this->filesSyncFromRemote();
        $this->dbImport();
    }

    /**
     * Creates local database dump.
     */
    function dbDump() {
        global $config;
        $this->_exec("mkdir -p ./database");
        $this->_exec("mysqldump -u $config->dbUser -p$config->dbPass $config->dbName > ./database/database.sql");
    }


    /**
     * Locally imports database dump, if it exists.
     */
    function dbImport() {
        global $config;
        if (file_exists('database/database.sql')) {
            $this->_exec("mysql -u $config->dbUser -p$config->dbPass $config->dbName < ./database/database.sql");
        } else {
            echo "No database file found.";
        }
    }

    /**
     * Push local files to remote server.
     *
     * Will ignore files in .gitignore.
     * Will include files in .rsync-include
     */
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

    /**
     * Make remote server import database dump.
     */
    function filesSyncToRemotePost() {
        $this->taskSshExec($this->host, $this->ssh_user)
            ->remoteDir($this->remote_path . '/site')
            ->exec('composer install')
            ->exec('php vendor/bin/robo db:import')
            ->run();
    }

    /**
     * Pull files from remote.
     */
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