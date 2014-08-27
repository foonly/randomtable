<?php

class randomtable {
    private $tables = Array();
    private $data = Array();
    private $set = Array();
    private $statement = "";
    private $include = Array();
    private $loaded = Array();

    public function __construct($raw=null,$name=null) {
        if (!is_null($raw)) $this->populate($raw,$name);
    }

    public function populate ($raw,$name=null,$reset=false) {
        if ($reset) {
            $this->tables = Array();
            $this->data = Array();
            $this->set = Array();
            $this->statement = "";
            $this->include = Array();
            $this->loaded = Array();
        }

        if (!empty($name) && !in_array($name,$this->loaded)) {
            $this->loaded[] = $name;
        }

        $raw = str_replace("\r","\n",$raw);
        $raw = str_replace("\n\n","\n",$raw);

        $data = preg_split("/\n#/","\n".$raw);

        $this->setStatement(array_shift($data));

        foreach ($data as $table) {
            $parts = explode("\n",$table,2);
            if ($parts[0] != "main" || !isset($this->tables[$parts[0]]))
            $this->buildTable($parts[0],$parts[1]);
        }
    }

    public function getStatement() {
        return $this->statement;
    }
    public function setStatement($value) {
        while (preg_match('/@([a-z][a-z0-9_-]*)/i',$value,$match)) {
            $this->include[] = $match[1];
            $value = trim(str_replace($match[0],"",$value));
        }
        if (empty($this->statement)) {
            $this->statement = trim($value);
        }
    }

    public function getInclude() {
        return $this->include;
    }
    public function popInclude() {
        if (count($this->include)) {
            return array_pop($this->include);
        } else {
            return false;
        }
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

    public function isLoaded($name) {
        return in_array($name,$this->loaded);
    }

    protected function buildTable($name,$data) {
        $name = strtolower($name);
        $table = Array();

        foreach (explode("\n",trim($data)) as $row) {
            $row = static::cleanup($row); // Remove comments
            $r = Array("w"=>0,"v"=>"");
            if (preg_match("/^([0-9]+)( [^0-9].*)?/",$row,$matches)) {
                if (!empty($matches[1])) { // Assign weight
                    $r['w'] = intval($matches[1]);
                }
                if (!empty($matches[2])) { // Assign value
                    $r['v'] = trim($matches[2]);
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

    protected function resolveTable($name,$random=null,$set=null,$delimiter=" ") {
        $table = &$this->tables[strtolower($name)];
        if (!is_null($set)) { // Assign pointer if defined otherwise var will be local, avoids extra code. (Ugly hack)
            $set = &$this->set[strtolower($set)];
        }
        if (!is_array($set)) $set = array();

        if (empty($table)) { // Requested table doesn't exist or is empty
            return "";
        }

        $return = "";
        $totalweight = 0;
        foreach ($table as $row) {
            if ($row['w'] > 0 && !in_array(strtolower($row['v']),$set)) { // Check for negative values and if in set
                $totalweight += $row['w'];
            }
        }
        if (is_null($random) && $totalweight > 0) {
            $random = rand(1,$totalweight); // Generate random number for weight
        }
        foreach ($table as $row) { // There might be a better way to do this, but I can't think of one
            $text = "";
            if ($row['w'] == 0) { // Always execute weight 0
                $text = trim($this->parse($row['v']));
            } elseif (!in_array(strtolower($row['v']),$set)) { // Check to see if in set
                $before = $random;
                $random -= $row['w'];
                if ($before > 0 && $random < 1) { // Matched weight
                    $set[] = strtolower($row['v']); // Add unparsed value to set
                    $text = trim($this->parse($row['v'])); // Do a recursive parse on the text
                }
            }
            if (!empty($text)) { // Add to return
                $return .= $text;
                if (trim($text) != '\n' || $delimiter != "\n") {
                    $return .= $delimiter;
                }
            }
        }
        return static::trimLines(str_replace('\n',"\n",$return));
    }

    public function generate ($table=null) {
        $this->data = array(); // Reset variable data
        if (is_null($table)) {
            if (empty($this->statement)) {
                return $this->resolveTable("main",null,null,"\n");
            } else {
                return $this->resolveTable($this->statement,null,null,"\n");
            }
        }
        return $this->resolveTable($table,null,null,"\n");
    }

    protected function parse($text) {
        $text = $this->parseCalc($text);
        $text = $this->parseIf($text);

        while (preg_match('/([$%])([a-z][a-z0-9_]*)(="[^"]*"|[^ [:cntrl:]\\\\]*)/i',$text,$match)) { //Look for table and variable references
            $rep = $match[1].$match[2];
            $name = $this->varName($match[2]);
            $opt = empty($match[3])?"":$match[3];
            if ($match[1] == "$") {
                preg_match('/^(\(([^)]+)\))?(\{([^}]+)\})?/i',$opt,$m);
                $rep .= $m[0]; // Add matched options to removed
                $random = empty($m[2])?null:static::calculate($this->parse($m[2]));
                $set = empty($m[4])?null:$m[4];
                $result = $this->resolveTable($name,$random,$set);

            }
            if ($match[1] == "%") {
                if (preg_match('!^([+*/-])?=(.*)!',$opt,$match)) { // Variable assignment
                    if ($this->parseVarDef($name,$match[2],empty($match[1])?null:$match[1]) === false) {
                        error_log("Error defining variable:\n".print_r($match,true));
                    }
                    $rep .= $opt; // Add opt to removed
                    $result = ""; // Empty result removes assignment from output.
                } else { // Get the variable contents
                    $result = $this->getVar($name);
                    if ($opt == "++") {
                        $this->setVar($name,$this->getVar($name)+1);
                        $rep .= $opt; // Add opt to removed
                    }
                    if ($opt == "--") {
                        $this->setVar($name,$this->getVar($name)-1);
                        $rep .= $opt; // Add opt to removed
                    }
                }
            }

            $text = preg_replace('/' . preg_quote( $rep, '/' ) . '/',$result,$text,1);
        }

        return $text;
    }

    protected function parseIf($text) {
        while (preg_match('/^\$if(.+)\$then(.*)$/i',$text,$match)) {
            $parts = explode(' $else ',$match[2],2);

            $result = $this->condition($match[1])?trim($parts[0]):trim(empty($parts[1])?'':$parts[1]);

            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$result,$text,1);
        }
        return $text;
    }

    protected function condition($condition) {
        $condition = static::calculate($this->parse(trim($condition)));

        while (preg_match('/^(.*) (lte?|gte?|eq) (.*)$/i',$condition,$match)) {
            switch ($match[2]) {
                case "lt":
                    return ($match[1] < $match[3]);

                case "lte":
                case "le":
                    return ($match[1] <= $match[3]);

                case "gt":
                    return ($match[1] > $match[3]);

                case "gte":
                case "ge":
                    return ($match[1] >= $match[3]);

                case "eq":
                    return ($match[1] == $match[3]);
            }
            return false;
        }

        return false;
    }

    protected function parseCalc ($text) {
        while (preg_match('/\[([^] ]+)\]/',$text,$match)) { //Look for []
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',static::calculate($this->parse($match[1])),$text,1);
        }
        return $text;
    }


    protected function parseVarDef($name,$def,$shorthand=null) {
        if (substr($def,0,1) == '"' && substr($def,-1) == '"') { // Assign quoted value
            $value = substr($def,1,-1);
        } else if (!is_null($shorthand)) { // Look for shorthand
            $value = $this->parse($this->getVar($name).$shorthand.$def); // Parse the value before assigning it.

        } else {
            $value = $this->parse($def); // Parse the value before assigning it.
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

    static public function rollDice($nr,$sides) {
        $result = 0;
        for ($i=0;$i<$nr;$i++){
            $result += rand(1,$sides);
        }
        return $result;
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

    static public function trimLines ($text) {
        $output = array();
        foreach (explode("\n",$text) as $line) {
            $output[] = trim($line);
        }
        return implode("\n",$output);
    }

    static public function calculate ($text) {
        while (preg_match('/([0-9]+)?D([0-9]+)/',$text,$match)) { //Look for die definitions
            $nr = empty($match[1])?1:$match[1]; // Number of dice
            $sides = $match[2];

            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',static::rollDice($nr,$sides),$text,1);
        }

        $recurse = false;
        while (preg_match('|(-?[0-9.]+)([*/])(-?[0-9.]+)|',$text,$match)) {
            if ($match[2] == "*") {
                $result = $match[1] * $match[3];
            } else {
                $result = $match[1] / $match[3];
            }
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$result,$text,1);
            $recurse = true;
        }

        while (preg_match('|(-?[0-9.]+)([+-])(-?[0-9.]+)|',$text,$match)) {
            if ($match[2] == "+") {
                $result = $match[1] + $match[3];
            } else {
                $result = $match[1] - $match[3];
            }
            $text = preg_replace('/' . preg_quote( $match[0], '/' ) . '/',$result,$text,1);
            $recurse = true;
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