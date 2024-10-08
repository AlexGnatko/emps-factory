<?php

class EMPS_AWStats {
    public $hostname = "";
    public $period = "";
    public $filename = "";
    private $map;

    public function report_filename() {
        $filename = "/var/lib/awstats/awstats".$this->period.".".$this->hostname.".txt";
        if (file_exists($filename)) {
            return $filename;
        }
        return false;
    }

    public function read_map() {
        $fh = fopen($this->filename, "rb");
        rewind($fh);
        $map = [];
        while(!feof($fh)){
            $line = fgets($fh);
            $x = explode(" ", $line);
            if(trim($x[0]) == 'BEGIN_MAP'){
                $count = intval($x[1]);
                for($i = 0; $i < $count; $i++){
                    $line = fgets($fh);
                    $x = explode(" ", $line);
                    $map[$x[0]] = intval($x[1]);
                }
            }
            if(trim($x[0]) == 'END_MAP'){
                break;
            }
        }
        $this->map = $map;
        fclose($fh);
    }

    public function read_awstats_section($code, $lines = 10) {
        $fh = fopen($this->filename, "rb");
        rewind($fh);
        $offset = $this->map['POS_'.$code];
        if (!$offset) {
            return false;
        }
        fseek($fh, $offset, SEEK_SET);
        $values = [];
        while(!feof($fh)){
            $line = fgets($fh);
            $x = explode(" ", $line);
            if($x[0] == 'BEGIN_'.$code){
                $count = intval($x[1]);
                if ($code == "OS") {
                    $count = intval($x[6]);
                }
                if ($count > $lines) {
                    $count = $lines;
                }
                for($i = 0; $i < $count; $i++){
                    $line = fgets($fh);
                    $line = trim($line);
                    $x = explode(" ", $line);
                    $rawcode = strval(array_shift($x));
                    $code = "_".$rawcode;
                    $values[$code] = ['name' => $rawcode, 'cols' => []];
                    foreach($x as $xv){
                        $values[$code]['cols'][] = $xv;
                    }
                }
                return $values;
            }
        }
        return $values;
    }

    public function index() {
        $filename = $this->report_filename();
        if (!$filename) {
            return false;
        }
        $this->filename = $filename;

        $this->read_map();

        $index = [];
        $sections = "GENERAL,TIME=24,VISITOR,DOMAIN,DAY=31,ROBOT,WORMS,SESSION,OS=1000,BROWSER,ERRORS,SIDER,SIDER_404";
        $x = explode(",", $sections);
        foreach ($x as $v) {
            $xx = explode("=", $v);
            $v = $xx[0];
            $lines = $xx[1];
            if (!$lines) {
                $lines = 10;
            }
            $index[$v] = $this->read_awstats_section($v, $lines);
        }

        return $index;
    }

    public function dt_to_dperiod($dt) {
        return date("mY", $dt);
    }


    public function dperiod_to_dt($period) {
        global $emps;

        $month = mb_substr($period, 0, 2);
        $year = mb_substr($period, 2, 4);
        $date = "01.{$month}.{$year} 12:00";
        $dt = $emps->parse_time($date);
        return $dt;
    }

    public function sanitize_ip($ip) {
        $x = explode(".", $ip, 4);
        $parts = [];
        foreach ($x as $v) {
            $v = intval($v);
            $parts[] = strval($v);
        }
        return implode(".", $parts);
    }
}

