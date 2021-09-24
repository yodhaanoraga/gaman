<?php
/*
The MIT License (MIT)
Copyright (c) 2021 GanDev

www.gaman.web.id

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/

/**
 * Class DB
 *
 * A simple wrapper for PDO.
 */
class DB extends PDO
{
    private $error;
    private $sql;
    private $bind;
    private $debugger = 0;
    public $working = "yes";

    public function __construct($dsn, $user = "", $passwd = "", $debug_level = 0)
    {
        $options = [
            PDO::ATTR_PERSISTENT       => true,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        ];
        $this->debugger = $debug_level;
        try {
            parent::__construct($dsn, $user, $passwd, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die("Could not connect to the DB - ".$this->error);
        }
    }

    private function debug()
    {
        global $_config;
        if (!$this->debugger) {
            return;
        }
        $error = ["Error" => $this->error];
        if (!empty($this->sql)) {
            $error["SQL Statement"] = $this->sql;
        }
        if (!empty($this->bind)) {
            $error["Bind Parameters"] = trim(print_r($this->bind, true));
        }

        $backtrace = debug_backtrace();
        if (!empty($backtrace)) {
            foreach ($backtrace as $info) {
                if ($info["file"] != __FILE__) {
                    $error["Backtrace"] = $info["file"]." at line ".$info["line"];
                }
            }
        }
        $msg = "";
        $msg .= "SQL Error\n".str_repeat("-", 50);
        foreach ($error as $key => $val) {
            $msg .= "\n\n$key:\n$val";
        }

        
            _log($msg);
        
    }

    private function cleanup($bind, $sql = "")
    {
        if (!is_array($bind)) {
            if (!empty($bind)) {
                $bind = [$bind];
            } else {
                $bind = [];
            }
        }

        foreach ($bind as $key => $val) {
            if (str_replace($key, "", $sql) == $sql) {
                unset($bind[$key]);
            }
        }
        return $bind;
    }

    public function single($sql, $bind = "")
    {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind, $sql);
        $this->error = "";
        try {
            $pdostmt = $this->prepare($this->sql);
            if ($pdostmt->execute($this->bind) !== false) {
                return $pdostmt->fetchColumn();
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->debug();
            return false;
        }
    }

    public function run($sql, $bind = "")
    {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind, $sql);
        $this->error = "";

        try {
            $pdostmt = $this->prepare($this->sql);
            if ($pdostmt->execute($this->bind) !== false) {
                if (preg_match("/^(".implode("|", ["select", "describe", "pragma"]).") /i", $this->sql)) {
                    return $pdostmt->fetchAll(PDO::FETCH_ASSOC);
                } elseif (preg_match("/^(".implode("|", ["delete", "insert", "update"]).") /i", $this->sql)) {
                    return $pdostmt->rowCount();
                }
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->debug();
            return false;
        }
    }

    public function row($sql, $bind = "")
    {
        $query = $this->run($sql, $bind);
        if (count($query) == 0) {
            return false;
        }
        if (count($query) > 1) {
            return $query;
        }
        if (count($query) == 1) {
            foreach ($query as $row) {
                $result = $row;
            }
            return $result;
        }
    }
}
