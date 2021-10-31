<?
/* t d b . i n c . p h p
 * ---------------------
 * TextDB 0.0.8
 * Written by Andy Brandt
 * Copyright 2000 Rump Roast Inc.
 * andy@rump-roast.com
 *
 * ======================================================
 *
 * three basic types: int, str, arr
 *
 * int file format: 
 *   number [4 bytes]
 *
 * str file format:
 *   length [4 bytes]
 *   string [length]
 *
 * arr file format:
 *   length [4 bytes]
 *     type [raw - 3 bytes]
 *     element 1 [type]
 *     ...
 *     element n [type]
 *
 * data file format:
 *   key [str]
 *   num fields [int]
 *     field name 1 [str]
 *     field type 1 [raw - 3 bytes]
 *     ...
 *     field name n [str]
 *     field type n [raw - 3 bytes]
 *   data...
 */

include("misc.inc.php");

function phpver()
{
    return floor(phpversion());
}

function tdbver()
{
    return "TextDB 0.0.7";
}

if (phpver() == 3)
    include("php3.inc.php");
    
class TextDB {
    var $active; // are we active?
    var $fp;     // main file pointer
    var $idx;    // index file pointer
    var $a;      // array of fields
    var $fields; // number of fields
    var $size;   // number of elements
    var $key;    // key field
    var $pos;    // current position
    var $auto;   // auto number key field?
    var $fname;
    var $fpname;
    var $idxname;
    var $orderby;
    var $ordertype;
    var $hashsize = 2;
    var $index;

    // constructor
    function TextDB()
    {
        $this->active = false;
    } // FileDB

    // opens the file given, and sets everything up.
    function open($file)
    {
        $this->active = false;
        $this->size = 0;
        $this->pos = 0;
        $this->auto = false;
        $this->fname = $file;
        $this->fpname = $file . ".dat";
        $this->idxname = $file . ".idx";
        $this->fp = @fopen($this->fpname, "r+");
        if (!$this->fp)
            return false;
        $this->idx = @fopen($this->idxname, "r+");
        if (!$this->idx)
        {
            fclose($this->fp);
            return false;
        }

        // get the key
        flock($this->fp, 1);
        $tempkey = $this->tdbread("str");
        flock($this->fp, 3);

        // is it auto?
        if (substr($tempkey, 0, 1) == "@")
        {
            $this->auto = true;
            $this->key = substr($tempkey, 1);
        } else
            $this->key = $tempkey;

        // get the number of fields
        flock($this->fp, 1);
        $this->fields = $this->tdbread("int");

        // read in the fields to our array
        for($i=0;$i<$this->fields;$i++)
        {
            $fieldname = $this->tdbread("str");
            $fieldtype = fread($this->fp, 3);
            if (substr($fieldname, 0, 1) == "*")
            {
                $findex = true;
                $fieldname = substr($fieldname, 1);
            }
            else
                $findex = false;
            $this->a[] = array($fieldname, $fieldtype);
            $this->index[$fieldname] = $findex;
        }
        flock($this->fp, 3);

        // find out how many records we have
        $this->size = filesize($this->idxname) / 4;
        $this->active = true;
        return true;
    } // open

    // create a new DB file (erasing the old one if it exists)
    function create($file, $newa, $key = false)
    {
        $this->active = false;
        $this->size = 0;
        $this->pos = 0;
        $this->auto = false;
        $this->fname = $file;
        $this->fpname = $file . ".dat";
        $this->idxname = $file . ".idx";
        $this->fp = fopen($this->fpname, "w+");
        if (!$this->fp)
            return false;
        $this->idx = fopen($this->idxname, "w+");
        if (!$this->idx)
        {
            fclose($this->fp);
            return false;
        }
        if ($key == true)
            $newkey = $newa[0][0];
        else
            $newkey = "null";
        if (substr($newkey, 0, 1) == "@")
        {
            $this->auto = true;
            $this->key = substr($newkey, 1);
            $newa[0][0] = substr($newa[0][0], 1);
        } else
            $this->key = $newkey;
        flock($this->fp, 2);
        $this->tdbwrite($newkey, "str");
        $this->tdbwrite(count($newa), "int");
        for($i=0;$i<count($newa);$i++)
        {
            $this->tdbwrite($newa[$i][0], "str");
            fwrite($this->fp, $newa[$i][1], 3);
        }
        flock($this->fp, 3);
        $this->a = $newa;
        $this->fields = count($newa);
        $this->active = true;
        return true;
    } // create

    // close the current DB.
    function close()
    {
        if ($this->active)
        {
            fclose($this->fp);
            fclose($this->idx);
            $this->fields = 0;
            $this->a = 0;
            $this->key = "";
            $this->size = 0;
            $this->pos = 0;
            $this->auto = false;
            $this->active = false;
            $this->fname = "";
            $this->fpname = "";
            $this->idxname = "";
            $this->orderby = "";
            $this->ordertype = "";
            $this->index = 0;
        }
    } // close

    // executes an sql query.
    function exec($q)
    {
        if ($this->active)
        {
            return $this->parse($q);
        }
        return false;
    }

    function parse($q)
    {
        $command = nextword(&$q);
        switch (strtolower($command))
        {
            case "create":
                break;
            case "drop":
                break;
            case "alter":
                break;
            case "rename":
                break;
            case "select":
                $fields = nextwords(&$q);
                while (strlen($q) > 0)
                {
                    $next = nextword(&$q);
                    switch (strtolower($next))
                    {
                        case "where":
                            $where = nextwhere(&$q, $w);
                            break;
                        case "order":
                            nextword(&$q);
                            $orderby = nextorder(&$q);
                            break;
                        case "limit":
                            $limit = nextwords(&$q);
                            break;
                    }
                }

/*                        -- debug --
                echo count($fields) . "<br>\n";
                for($i=0;$i<count($fields);$i++)
                    echo $fields[$i] . "<br>\n";
*/

                for($i=0; $i<$this->size; $i++)
                {
					if ($odir == "desc")
						$tmp = $this->get($this->size - 1 - $i);
					else
	                    $tmp = $this->get($i);

                    if (isset($where) && $where)
                    {
                        $add = expr($tmp[$w[0][fld]], $w[0][oper], $w[0][crit]);
                        if ($add == false)
                            $add = false;
                        else
                            $add = true;
                        $y = 0;
                        while (isset($w[$y][andor]))
                        {
                            $ao = $w[$y][andor];
                            $y++;
                            $newexp = expr($tmp[$w[$y][fld]], $w[$y][oper], $w[$y][crit]);
                            if ($ao == "and")
                            {
                                $add = ($add and $newexp);
                            }
                            else if ($ao == "or")
                            {
                                $add = ($add or $newexp);
                            }
                         }
                    }
                    else
                        $add = true;
                    
                    if ($fields[0] == "*")
                    {
                        if ($add)
                            $ret[] = $tmp;
                    }
                    else
                    {
                        if ($add)
                        {
                            for($j=0;$j<count($fields);$j++)
                                $tmparr[$fields[$j]] = $tmp[$fields[$j]];
                            $ret[] = $tmparr;
                            unset($tmparr);
                        }
                    }
                }
                if (is_array($orderby))
                {
/*                              -- debug --
                    echo "** " . count($orderby) . "<br>\n";
                    for($m=0;$m<count($orderby);$m++)
                        echo $orderby[$m][val] . " -> " . $orderby[$m][dir] . "<br>\n";
                    echo "**<br>\n";
*/

                    if (is_array($ret))
                        $ret = $this->tdbsort($ret, $orderby);
                }
 
                if (is_array($limit))
                {
                    for($e=0;$e<((count($ret) < $limit[0]) ? count($ret) : $limit[0]);$e++)
                        $newret[] = $ret[$e];
                    $ret = $newret;
                    unset($newret);
                }

                return $ret;
            case "insert":
                break;
            case "delete":
                break;
            case "replace":
                break;
            case "update":
                break;
        }
        return true;
    }

    function tdbcmp($a, $b)
    {
        return compare($a, $b, $this->orderby, $this->ordertype);
    }

    function tdbsort($arr, $orderby)
    {
        for($i=0;$i<count($orderby);$i++)
        {
            $j=0;
            while ($this->a[$j][0] != $orderby[$i][val]) { $j++; }
            $ot[] = $this->a[$j][1];
        }
        
        $this->ordertype = $ot;
        $this->orderby = $orderby;
        if (gettype($arr) != 'NULL')
            usort($arr, array($this, "tdbcmp"));
        return $arr;
    }

    function get($elem)
    {
        if ($this->active)
        {
            if ($elem < 0 or $elem >= $this->size)
                return false;
            $this->lockf(1);
            fseek($this->idx, $elem * 4);
            $pos = $this->bin2dec(fread($this->idx, 4), false);
            fseek($this->fp, $pos);
            for($i=0;$i<$this->fields;$i++)
			{
	            $arr[$this->a[$i][0]] = $this->tdbread($this->a[$i][1]);					
			}
            $this->pos = $elem + 1;
            $this->unlockf();
            return $arr;
        }
        return false;
    } // get

    // indexed find
    function ifind($invar, $tofind)
    {
        if ($this->index[$invar])
        {
            $iidx = $fname . "-" . $invar . ".idx";
            $idat = $fname . "-" . $invar . ".dat";
            $fiidx = fopen($iidx, "w+");
            $fidat = fopen($idat, "w+");
            fclose($fiidx);
            fclose($fidat);
        }
        else
            return find($invar, $tofind);
    }

    function iwrite($cpos)
    {
        echo count($this->index);
        reset($this->index);
        while (list($key, $v) = each($this->index))
        {
            if ($v)
            {
                $iidx = $this->fname . "-" . $key . ".idx";
                $fiidx = fopen($iidx, "w+");
                fclose($fiidx);
            }
        }
    }

    // finds all matching things.
    function findall($invar = "", $tofind = "")
    {
        for($i=0;$i<$this->size;$i++)
        {
            $tmp = $this->get($i);
            if (($invar == "" and $tofind == "") or ($tmp[$invar] == $tofind))
                $arr[] = $tmp;
        }
        if (count($arr) > 0)
            return $arr;
        return false;
    } // findall

    function findcount($invar, $tofind)
    {
        $cnt = 0;
        for($i=0;$i<$this->size;$i++)
        {
            $tmp = $this->get($i);
            if ($tmp[$invar] == $tofind)
                $cnt++;
        }
        return $cnt;
    } // findcount

    function find($invar, $tofind)
    {
        if ($invar == $this->key)
        {
            return $this->bsearch($invar, 0, $this->size-1, $tofind);
        }
        else
        {
            for($i=0;$i<$this->size;$i++)
            {
                $tmp = $this->get($i);
                if ($tmp[$invar] == $tofind)
                    return $i;
            }
        }
        return -1;
    } // find

    function bsearch($invar, $first, $last, $value)
    {
        //echo "invar = $invar; first = $first; last = $last; value = $value;<br>\n";
        while ($first <= $last)
        {
            $mid = floor(($first + $last) / 2);
            //echo "mid = $mid;<br>\n";
            $t = $this->get($mid);
            if ($t[$invar] == $value)
                return $mid;
            if ($value < $t[$invar])
                $last = $mid - 1;
            else
                $first = $mid + 1;
        }
        return -1;
    }

    function erase($elem)
    {
        if ($this->active)
        {
            flock($this->idx, 2);
            $len = filesize($this->idxname);
            fseek($this->idx, 0);
            $buf = fread($this->idx, $len);
            fclose($this->idx);
            $this->idx = fopen($this->idxname, "w+");
            $newidx = substr($buf, 0, $elem * 4) . substr($buf, $elem * 4 + 4);
            fwrite($this->idx, $newidx, $len - 4);
            $this->size--;
            flock($this->idx, 3);
            return true;
        }
        return false;
    }
    
    function put($arr, $edit = false)
    {
        if ($this->active)
        {
            fclose($this->fp);
            fclose($this->idx);
            $this->fp = fopen($this->fpname, "r+");
            $this->idx = fopen($this->idxname, "r+");
            $arr = array_values($arr);

            // check to make sure we don't have a dupe.
            if (!$edit)
            {
                if ($this->key != "null" && $this->auto == false)
                {
                    $foo = $this->find($this->key, $arr[0]);
                    if ($foo > -1)
                    {
                        echo "duplicate key value (" . $arr[0] . ").<br>\n";
                        return false;
                    }
                }
            }
            clearstatcache();
            // go to the end of the file
            fseek($this->fp, filesize($this->fpname));
            // get the position
            $idxnum = $this->dec2bin(ftell($this->fp));

            // auto-increment if need be.
            if ($this->auto == true && $edit == false)
            {
                if ($this->size > 0)
                {
                    $this->lockf(1);
                    fseek($this->idx, filesize($this->idxname)-4);
                    $t = $this->bin2dec(fread($this->idx, 4));
                    fseek($this->fp, $t);
                    $t = $this->tdbread("int");
                    $arr[0] = $t + 1;
                    $this->unlockf();
                } else
                    $arr[0] = 0;
            }

            // write the index
            if ($edit)
            {
                $z = $this->find($this->a[0][0], $arr[0]);
                if ($z == -1)
                    return false;
                fseek($this->idx, $z * 4);
            }
            else
            {
                fseek($this->idx, filesize($this->idxname));
            }

            flock($this->idx, 2);
            fwrite($this->idx, $idxnum);
            flock($this->idx, 3);

            // write the index tables...
//            $this->iwrite($idxnum);
            
            fseek($this->fp, filesize($this->fpname));

            // and write the data...
            flock($this->fp, 2);
            for($i=0;$i<count($arr);$i++)
                $this->tdbwrite($arr[$i], $this->a[$i][1]);
            flock($this->fp, 3);
            
            if (!$edit)
                $this->size++;

            return true;
        }
        return false;
    } // put

    function add($data)
    {
        return $this->put($data);
    }

    function edit($data)
    {
        return $this->put($data, true);
    }

    function addfield($newfield)
    {
        return $this->rewrite("add", 0, $newfield);
    }

    function delfield($fid)
    {
        return $this->rewrite("del", $fid);
    }

    function editfield($fid, $newname)
    {
        return $this->rewrite("edit", $fid, $newname);
    }

    function compact()
    {
        return $this->rewrite();
    }

    function rewrite($action = "", $fid = "", $fn = "")
    {
        if ($this->active)
        {
            fclose($this->fp);
            $this->fp = fopen($this->fpname, "r+");
            flock($this->fp, 1);
            $newdat = $this->tdbread("str", true);
            $n = $this->tdbread("int");
            if ($action == "add")
                $newdat .= $this->dec2bin($n + 1);
            else if ($action == "del")
                $newdat .= $this->dec2bin($n - 1);
            else
                $newdat .= $this->dec2bin($n);
            for($j=0;$j<$n;$j++)
            {
                $fname = $this->tdbread("str", true);
                $ftype = fread($this->fp, 3);
                if ($action == "del")
                {
                    if ($fid != $j)
                        $newdat .= $fname . $ftype;
                }
                else if ($action == "edit")
                {
                    if ($fid == $j)
                        $newdat .= $this->tdbstr($fn[0]) . $fn[1];
                    else
                        $newdat .= $fname . $ftype;
                }
                else
                    $newdat .= $fname . $ftype;
            }
            if ($action == "add")
                $newdat .= $this->tdbstr($fn[0]) . $fn[1];
            fclose($this->idx);
            $this->idx = fopen($this->idxname, "r+");
            flock($this->idx, 1);
            for($i=0;$i<$this->size;$i++)
            {
                fseek($this->fp, $this->bin2dec(fread($this->idx, 4)));
                $newidx .= $this->dec2bin(strlen($newdat));
                for($j=0;$j<$n;$j++)
                {
                    if ($action == "edit")
                    {
                        $data = $this->tdbread($this->a[$j][1]);
                        if ($fid == $j and $this->a[$j][1] != $fn[1])
                            $newdat .= $this->tdbconv($data, $fn[1]);
                        else
                            $newdat .= $this->tdbconv($data, $this->a[$j][1]);
                    }
                    else if ($action == "del")
                    {
                        $data = $this->tdbread($this->a[$j][1], true);
                        if ($fid != $j)
                            $newdat .= $data;
                    }
                    else
                        $newdat .= $this->tdbread($this->a[$j][1], true);
                }
                if ($action == "add")
                {
                    if ($fn[1] == "str")
                        $y = "";
                    else if ($fn[1] == "int")
                        $y = 0;
                    else if ($fn[1] == "arr")
                        $y = array(array(0, "int"));
                    $newdat .= $this->tdbconv($y, $fn[1]);
                }
            }
            fclose($this->idx);
            fclose($this->fp);
            $this->idx = fopen($this->idxname, "w+");
            flock($this->idx, 2);
            fwrite($this->idx, $newidx, strlen($newidx));
            $this->fp = fopen($this->fpname, "w+");
            flock($this->fp, 2);
            fwrite($this->fp, $newdat, strlen($newdat));
            flock($this->idx, 3);
            flock($this->fp, 3);
            if ($action == "add")
                $this->a[] = $fn;
            else if ($action == "del")
            {
                $olda = $this->a;
                unset($this->a);
                for($j=0;$j<count($this->a);$j++)
                {
                    if ($fid == $j)
                        $this->a[] = $olda[$j];
                }
                unset($olda);
            }
            else if ($action == "edit")
                $this->a[$fid] = $fn;
            $this->fields = count($this->a);
            return true;
        }
        return false;
    }

    // converts from old style data files to new style.
    function convert($fn, $newformat)
    {
        if (!$this->active)
        {
            $this->fpname = $fn . ".dat";
            $this->idxname = $fn . ".idx";
            $this->fp = @fopen($this->fpname, "r+");
            if ($this->fp == false)
                return false;
            $this->idx = @fopen($this->idxname, "r+");
            if ($this->idx == false)
            {
                fclose($this->fp);
                return false;
            }
            $this->size = filesize($this->idxname) / 4;
            flock($this->fp, 1);
            $newdat = $this->tdbstr(chop(fgets($this->fp, 4096)));
            $n = chop(fgets($this->fp, 4096));
            $newdat .= $this->dec2bin($n);
            for($i=0;$i<$n;$i++)
            {
                $fieldname = chop(fgets($this->fp, 4096));
                $newdat .= $this->tdbstr($fieldname);
                $newdat .= $newformat[$i];
                $this->a[] = array($fieldname, $newformat[$i]);
            }
            $newidx = "";
            flock($this->idx, 1);
            for($i=0;$i<$this->size;$i++)
            {
                $newidx .= $this->dec2bin(strlen($newdat));
                fseek($this->fp, $this->bin2dec(fread($this->idx, 4)));
                for($j=0;$j<$n;$j++)
                    $newdat .= $this->tdbconv(chop(fgets($this->fp, 4096)), $this->a[$j][1]);
            }
            $this->unlockf();
            $this->fflush("w+");
            $this->lockf();
            fwrite($this->fp, $newdat, strlen($newdat));
            fwrite($this->idx, $newidx, strlen($newidx));
            $this->unlockf();
            return true;
        }
        return false;
    }

    function fflush($mode = "r+")
    {
        fclose($this->fp);
        fclose($this->idx);
        $this->fp = fopen($this->fpname, $mode);
        $this->idx = fopen($this->idxname, $mode);
    }

    function lockf($oper = 2)
    {
        flock($this->fp, $oper);
        flock($this->idx, $oper);
    }

    function unlockf()
    {
        flock($this->fp, 3);
        flock($this->fp, 3);
    }

    function tdbstr($data)
    {
        return $this->dec2bin(strlen($data)) . $data;
    }

    function tdbconv($data, $type)
    {
        switch ($type)
        {
            case "int":
                if (!is_long($data))
                    $data = 0;
                $str = $this->dec2bin($data);
                break;
            case "str":
                if (is_array($data))
                    if (is_array($data[0]))
                        $data = $data[0][0];
                    else
                        $data = $data[0];
                $str = $this->dec2bin(strlen($data)) . $data;
                break;
            case "arr":
                if (is_long($data))
                    $data = array(array($data, "int"));
                else if (is_string($data))
                    $data = array(array($data, "str"));
                $str = $this->dec2bin(count($data));
                for($i=0;$i<count($data);$i++)
                    $str .= $data[$i][1] . $this->tdbconv($data[$i][0], $data[$i][1]);
                break;
        }
        return $str;
    }

    function tdbread($type, $raw = false, $debug = false)
    {
        $len = $this->bin2dec(fread($this->fp, 4), $debug);
        if ($type == "int")
        {
            if ($raw == true)
                return $this->dec2bin($len);
            else
                return $len;
        }
        else if ($type == "arr")
        {
            for($i=0;$i<$len;$i++)
            {
                $atype = fread($this->fp, 3);
                $arr[] = array($this->tdbread($atype, $raw), $atype);
            }
            if ($raw == true)
            {
                $ret = $this->dec2bin($len);
                for($i=0;$i<count($arr);$i++)
                    $ret .= $arr[$i][1] . $arr[$i][0];
                return $ret;
            }
            else
                return $arr;
        }
        else if ($type == "str")
        {
            $str = fread($this->fp, $len);
            if ($raw == true)
                return $this->dec2bin($len) . $str;
            else
                return $str;
        }
    }

    function tdbwrite($data, $type)
    {
        switch ($type)
        {
            case "int":
                fwrite($this->fp, $this->dec2bin($data), 4);
                break;
            case "str":
                fwrite($this->fp, $this->dec2bin(strlen($data)), 4);
                fwrite($this->fp, $data, strlen($data));
                break;
            case "arr":
                fwrite($this->fp, $this->dec2bin(count($data)), 4);
                for($i=0;$i<count($data);$i++)
                {
                    $atype = $data[$i][1];
                    fwrite($this->fp, $atype, 3);
                    $this->tdbwrite($data[$i][0], $atype);
                }
                break;
        }
        return true;
    }

    function dec2bin($data)
    {
        $hex = dechex($data);
        while (strlen($hex) < 8)
            $hex = "0" . $hex;
        $ret = chr(hexdec(substr($hex, 0, 2))) .
                  chr(hexdec(substr($hex, 2, 2))) .
                  chr(hexdec(substr($hex, 4, 2))) .
                  chr(hexdec(substr($hex, 6, 2)));
        return $ret;
    } // dec2bin

    function bin2dec($data, $debug = false)
    {
		$x = "";
        while (strlen($data) > 0)
        {
			if ($debug) echo "[" . dechex(ord($data)) . "]<br>\n";
            $x .= pad(dechex(ord($data)));
            $data = substr($data, 1);
        }
        return hexdec($x);
    } // bin2dec
}
?>
