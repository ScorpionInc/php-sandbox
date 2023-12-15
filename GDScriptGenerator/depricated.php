<?php
//String Function(s)
function implode_r(string $glue, $a, bool $reverse = false)
{
	//Returns string values of elements joined by glue recursively.
	if(is_string($a))
		return $a;
	if(is_array($a))
	{
		//Handle Array
		$a_count = count($a);
		if($a_count <= 0)
			return "";
		if($reverse)
			$a = array_reverse($a);
		$buffer = "";
		foreach($a as $e)
		{
			$buffer .= implode_r($glue, $e, $reverse);
			$buffer .= $glue;
		}
		return substr($buffer, 0, strlen($buffer) - strlen($glue));
	}
	return strval($a);
}
?>