<?

function array_values($a)
{
    if (is_array($a))
    {
        for ($i=0;$i<count($a);$i++)
        {
            $y = each($a);
            $z[$i] = $y[value];
        }
        return $z;
    }
    return false;
}

function array_reverse($a)
{
    if (is_array($a))
    {
        for($i=count($a)-1;$i>=0;$i--)
            $z[] = $a[$i];
        return $z;
    }
    return false;
}

?>
