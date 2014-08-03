<?php

class randomtable {
    private $tables = Array();
    private $data = Array();
    private $set = Array();
    private $statement;



    public function __construct($raw) {
        $raw = str_replace("\r","",$raw);
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
        $this->data[$name] = static::calculate($value);
    }

    protected function buildTable($name,$data) {
        $name = strtolower($name);
        $table = Array();

        foreach (explode("\n",trim($data)) as $row) {
            $row = static::cleanup($row); // Remove comments
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

    protected function resolveTable($name,$random=null,$set=null) {
        $table = &$this->tables[strtolower($name)];
        if (empty($table)) { // Requested table doesn't exist or is empty
            return "";
        }

        $uc = static::checkCase($name);

        $return = "";
        $totalweight = 0;
        foreach ($table as $row) {
            if ($row['w'] > 0) // Just in case of negative values
                $totalweight += $row['w'];
        }
        if (is_null($random) && $totalweight > 0) {
            $random = rand(1,$totalweight); // Generate random number for weight
        }
        foreach ($table as $row) { // There might be a better way to do this, but I can't think of one
            if ($row['w'] == 0) { // Always execute weight 0
                $return = trim($return." ".$this->parse(trim($row['v'])));
            } else { // Check to see if executed
                $before = $random;
                $random -= $row['w'];
                if ($before > 0 && $random < 1) { // Matched weight
                    $text = $this->parse(trim($row['v'])); // Do a recursive parse on the text
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
        if (is_null($table)) {
            return $this->parse($this->statement);
        }
        return $this->resolveTable($table);
    }

    protected function parse($text) {
        $text = $this->parseDice($text);

        while (preg_match('/([$%])([a-z][a-z0-9_-]*)([^ [:cntrl:]]*)/i',$text,$match)) { //Look for table references
            $rep = $match[1].$match[2];
            $name = $this->varName($match[2]);
            $opt = empty($match[3])?"":$match[3];
            if ($match[1] == "$") {
                preg_match('/^(\(([^)]+)\))?(\{([^}]+)\})?/i',$opt,$m);
                $rep .= $m[0]; // Add matched options to removed
                $random = empty($m[2])?null:static::calculate($m[2]);
                $set = empty($m[4])?null:$m[4];
                $result = $this->resolveTable($name,$random,$set);

            }
            if ($match[1] == "%") {
                if (substr($opt,0,1) == "=" || substr($opt,0,1) == "+" || substr($opt,0,1) == "-") { // Variable assignment
                    if ($this->parseVarDef($name,$opt) === false) {
                        print_r($match);
                    }
                    $rep .= $opt; // Add opt to removed
                    $result = ""; // Empty result removes assignment from output.
                } else { // Get the variable contents
                    $result = $this->getVar($name);
                }
            }

            $text = preg_replace('/' . preg_quote( $rep, '/' ) . '/',$result,$text,1);
        }


        return $text;
    }

    protected function parseDice ($text) {
        while (preg_match('/\[([0-9]+)?D([0-9]+)\]/',$text,$match)) { //Look for die definitions
            $nr = empty($match[1])?1:$match[1]; // Number of dice
            $sides = $match[2];

            $result = 0;
            for ($i=0;$i<$nr;$i++){
                $result += rand(1,$sides);
            }

            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$result,$text,1);
        }
        return $text;
    }

    protected function parseVarDef($name,$def) {
        if ($def == "++") {
            $value = $this->getVar($name)."+1";
        }
        if ($def == "--") {
            $value = $this->getVar($name)."-1";
        }
        if (substr($def,0,2) == '="' && substr($def,-1) == '"') {
            $value = substr($def,2,-1);
        }
        if (substr($def,0,1) == "=") {
            $value = $this->parse(substr($def,1)); // Parse the value before assigning it.
            switch (substr($value,0,1)) {
                case "+":
                case "-":
                case "*":
                case "/":
                    $value = $this->getVar($name).$value;
                    break;
            }

        }
        if (isset($value)) {
            $this->setVar($name,$value);
            return true;
        }
        return false;
    }

    protected function varName ($name) {
        $name = strtolower(trim($name));
        return $name;
    }

    static public function cleanup ($text) {
        return preg_replace('/;.*$/','',$text);
    }

    static public function checkCase ($text) {
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
        $recurse = false;
        while (preg_match('|([0-9.]+)([*/])([0-9.]+)|',$text,$match)) {
            if ($match[2] == "*") {
                $result = $match[1] * $match[3];
            } else {
                $result = $match[1] / $match[3];
            }
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$result,$text,1);
        }

        while (preg_match('|([0-9.]+)([+-])([0-9.]+)|',$text,$match)) {
            if ($match[2] == "+") {
                $result = $match[1] + $match[3];
            } else {
                $result = $match[1] - $match[3];
            }
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$result,$text,1);
        }

        while (preg_match('/(max|min|avg)\(([^)]+)\)/',$text,$match)) {
            $parts = explode(",",$match[2]);

            $result = null;
            switch ($match[1]) {
                case "max":
                    foreach ($parts as $part) {
                        if ($part > $result) $result = $part;
                    }
                break;
                case "min":
                    foreach ($parts as $part) {
                        if ($part < $result || is_null($result)) $result = $part;
                    }
                break;
                case "avg":
                    foreach ($parts as $part) {
                        $result += $part;
                    }
                    $result = $result / count($parts);
                break;
            }
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$result,$text,1);
            $recurse = true;
        }

        if ($recurse) {
            return static::calculate($text);
        } else {
            return $text;
        }
    }
}