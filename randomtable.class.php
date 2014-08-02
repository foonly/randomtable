<?php

class randomtable {
    private $tables = Array();
    private $data = Array();
    private $statement;



    public function __construct($raw) {
        $data = preg_split("/\n#/","\n".$raw);

        $this->setStatement(array_shift($data));

        foreach ($data as $table) {
            $parts = explode("\n",$table,2);
            $this->buildTable($parts[0],$parts[1]);
        }
    }

    public function setStatement($value) {
        $this->statement = trim($value);
    }

    public function getVar($name) {
        $value = "";
        if (isset($this->data[$name])) {
            $value = $this->data[$name];
        }
        return $value;
    }

    public function setVar($name,$value) {
        echo $value."\n";
        echo static::calculate($value)."\n\n";

        $this->data[$name] = static::calculate($value);
    }

    protected function buildTable($name,$data) {
        $name = strtolower($name);
        $table = Array();

        foreach (explode("\n",trim($data)) as $row) {
            $row = $this->cleanup($row); // Remove comments
            $r = Array("w"=>0,"v"=>"");
            if (preg_match("/^([0-9]+)([^0-9].*)?/",$row,$matches)) {
                if (!empty($matches[1])) { // Assign weight
                    $r['w'] = 0+$matches[1];
                }
                if (!empty($matches[2])) { // Assign value
                    $r['v'] = $matches[2];
                }
            } else {
                $r['v'] = $row;
            }
            if (!empty($r['w']) || !empty($r['v'])) {
                $table[] = $r;
            }
        }

        $this->tables[$name] = $table;
    }

    protected function resolveTable($name) {
        $table = &$this->tables[strtolower($name)];
        if (empty($table)) { // Requested table doesn't exist or is empty
            return "";
        }

        $uc = $this->checkCase($name);

        $return = "";
        $totalweight = 0;
        foreach ($table as $row) {
            if ($row['w'] > 0) // Just in case of negative values
                $totalweight += $row['w'];
        }
        $r = ($totalweight == 0)?0:rand(1,$totalweight); // Generate random number for weight
        foreach ($table as $row) { // There might be a better way to do this, but I can't think of one
            if ($row['w'] == 0) { // Always execute weight 0
                $return = trim($return." ".$this->parse(trim($row['v'])));
            } else { // Check to see if executed
                $before = $r;
                $r -= $row['w'];
                if ($before > 0 && $r < 1) { // Matched weight
                    $text = strtolower($this->parse(trim($row['v']))); // Do a recursive parse on the text
                    if ($uc > 1) {
                        $text = ucwords($text);
                    } elseif ($uc > 0) {
                        $text = ucfirst($text);
                    }
                    $return = trim($return." ".$this->parse(trim($text)));
                }
            }
        }
        return trim($return);
    }

    public function generate ($table=null) {
        $this->data = array(); // Reset variable data
        return $this->resolveTable($table);
    }

    protected function parse($text) {

        while (preg_match('/\$([a-z0-9_-]+)/i',$text,$match)) { //Look for table references
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$this->resolveTable($match[1]),$text,1);
        }

        while (preg_match('/&([a-z0-9_-]+)=("[^"]*"|[^ ]+)/i',$text,$match)) { // Set variables
            $name = trim($match[1]);
            $value = $this->parse(trim($match[2],' "'));

            switch (substr($value,0,1)) {
                case "+":
                case "-":
                case "*":
                case "/":
                    $value = $this->getVar($name).$value;
                break;
            }
            $this->setVar($name,$value);

            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/','',$text,1); // Remove statement from text
        }

        while (preg_match('/&([a-z0-9_-]+)/i',$text,$match)) { // Show variables
            $name = trim($match[1]);

            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$this->getVar($name),$text,1);
        }

        return $text;
    }

    protected function cleanup ($text) {
        return preg_replace('/;.*$/','',$text);
    }

    protected function checkCase ($text) {
        if (ctype_upper(substr($text,0,1))) { // Check case
            if (ctype_upper(substr($text,1,1))) {
                return 2;
            } else {
                return 1;
            }
        }
        return 0;
    }

    static public function calculate ($text) {
        while (preg_match('|([0-9.]+)([*/])([0-9.]+)|',$text,$match)) {
            if ($match[2] == "*") {
                $value = $match[1] * $match[3];
            } else {
                $value = $match[1] / $match[3];
            }
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$value,$text,1);
        }
        while (preg_match('|([0-9.]+)([+-])([0-9.]+)|',$text,$match)) {
            if ($match[2] == "+") {
                $value = $match[1] + $match[3];
            } else {
                $value = $match[1] - $match[3];
            }
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$value,$text,1);
        }
        return $text;
    }
}