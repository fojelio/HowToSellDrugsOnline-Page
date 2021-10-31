<?
// misc functions.

function binsearch($arr, $first, $last, $value)
{
    while ($first <= $last)
    {
        $mid = ($first + $last) / 2;
        if ($arr[$mid] == $value)
            return $mid;
        if ($value < $arr[$mid])
            $last = $mid - 1;
        else
            $first = $mid + 1;
    }
    return false;
}

function nextword($str)
{
    $pos = tdbstrpos($str, " ,");
    if ($pos == false)
    {
        $ret = $str;
        $str = "";
        return $ret;
    }
    $ret = substr($str, 0, $pos);
    $str = ltrim(substr($str, $pos + 1));
    return $ret;
}

function nextwhere($str, &$where)
{
    $opers = "=<>!$~";
    $done = false;
    while (!$done)
    {
        $pos = tdbstrpos($str, $opers);
        $arr[fld] = trim(substr($str, 0, $pos));
        $pos2 = $pos + 1;
        while (matchchar($str[$pos2], $opers))
            $pos2++;
        $arr[oper] = substr($str, $pos, $pos2-$pos);
        $str = ltrim(substr($str, $pos2));
        if ($str[0] == '"' or $str[0] == "'")
        {
            $quot = $str[0];
            $str = substr($str, 1);
            $pos = strpos($str, $quot);
        }
        else
            $pos = strpos($str, " ");
        if (validpos($pos))
        {
            $arr[crit] = trim(substr($str, 0, $pos));
            $str = trim(substr($str, $pos+1));
        }
        else
        {
            $arr[crit] = trim(substr($str, 0));
            $str = "";
        }
        $done = true;
        if (strlen($str) > 0)
        {
            $pos = strpos($str, " ");
            if (validpos($pos))
            {
                $andor = trim(substr($str, 0, $pos));
                if ((strtolower($andor) == "and") or
                    (strtolower($andor) == "or"))
                {
                    $str = trim(substr($str, $pos+1));
                    $arr[andor] = $andor;
                    $done = false;
                }
            }
        }
        $where[] = $arr;
        unset($arr);
    }
    return true;
}

function nextorder($str)
{
    $ddir = "asc";
    $going = true;
    $i = 0;
    while ($going)
    {
        if ($str[$i] == ",")
        {
            if (!is_string($dir))
                $dir = $ddir;
            $o[] = array("val" => trim($word), "dir" => $dir);
            unset($word);
            unset($dir);
        }
        else if ($str[$i] == " ")
        {
            $x = $i-1;
            while ($str[$x] == " ")
                $x--;
            if ($str[$x] != ",")
            {
                do
                {
                    $i++;
                } while ($str[$i] == " ");

                if (strtolower(substr($str, $i, 3)) == "asc")
                {
                    $dir = substr($str, $i, 3);
                    $i += 3;
                }
                else if (strtolower(substr($str, $i, 4)) == "desc")
                {
                    $dir = substr($str, $i, 4);
                    $i += 4;
                }
                else if ($str[$i] != ",")
                {
                    $going = false;
                    if (!is_string($dir))
                        $dir = $ddir;
                    $o[] = array("val" => trim($word), "dir" => $dir);
                }
                $i--;
            }
        }
        else if ($i >= strlen($str))
        {
            $going = false;
            if (!is_string($dir))
                $dir = $ddir;
            $o[] = array("val" => trim($word), "dir" => $dir);
        }
        else
            $word .= $str[$i];
        $i++;
    }
    $str = ltrim(substr($str, $i));
    if (is_array($o))
        return $o;
    else
        return false;
}

// ex: the string "one,two, three, four" will return an array with
function nextwords($str)
{
    $going = true;
    $i = 0;
    $brak = 0;
    while ($going)
    {
        if ($str[$i] == ",")
        {
            $words[] = trim($word);
            unset($word);
        }
        else if ($str[$i] == " ")
        {
            $x = $i-1;
            while ($str[$x] == " ")
                $x--;
            if ($str[$x] != ",")
            {
                do
                {
                    $i++;
                } while ($str[$i] == " ");

                if ($str[$i] != ",")
                {
                    $going = false;
                    $words[] = trim($word);
                }
                $i--;
            }
        }
        else if ($i >= strlen($str))
        {
            $going = false;
            $words[] = trim($word);
        }
        else if ($str[$i] == "(")
        {
            $brak++;
        }
        else if ($str[$i] == ")")
        {
            $brak--;
        }
        else
            $word .= $str[$i];
        $i++;
    }
    $str = ltrim(substr($str, $i));
    if (is_array($words))
        return $words;
    else
        return false;
}

function tdbstrpos($haystack, $needle)
{
    for($i=0;$i<strlen($needle);$i++)
    {
        $tmp = strpos($haystack, substr($needle, $i, 1));
        if (validpos($tmp))
            $res[] = $tmp;
    }
    if (is_array($res))
        return min($res);
    else
        return false;
}

function validpos($pos)
{
    if (floor(phpversion()) == 3)
    {
        if (is_string($pos) && !$pos)
            return false;
    }
    else
    {
        if ($pos === false)
            return false;
    }
    return true;
}

function matchchar($char, $chars)
{
    for($i=0;$i<strlen($chars);$i++)
    {
        if ($char == $chars[$i])
            return true;
    }
    return false;
}

function expr($left, $oper, $right)
{
    switch ($oper)
    {
        case "~=":
            return eregi($right, $left);
        case "$=":
            return ereg($right, $left);
        case "=":
            return ($left == $right);
        case "!=":
            return ($left != $right);
        case ">":
            return ($left > $right);
        case ">=":
            return ($left >= $right);
        case "<":
            return ($left < $right);
        case "<=":
            return ($left <= $right);
        default:
            return false;
    }
}

function compare($a, $b, $orderby, $type, $x = 0)
{
    if ($a[$orderby[$x][val]] == $b[$orderby[$x][val]])
    {
        if ($x >= count($orderby))
            return 0;
        else
            return compare($a, $b, $orderby, $type, $x+1);
    }
    switch ($type[$x])
    {
        case "int":
            $r = ($a[$orderby[$x][val]] < $b[$orderby[$x][val]]) ? -1 : 1;
            return ($orderby[$x][dir] == "asc") ? $r : -$r;
        case "str":
            $r = strcmp($a[$orderby[$x][val]], $b[$orderby[$x][val]]);
            return ($orderby[$x][dir] == "asc") ? $r : -$r;
    }
}

function pad($str, $len=2)
{
	while (strlen($str) < $len)
		$str = "0" . $str;
	return $str;
}

?>
