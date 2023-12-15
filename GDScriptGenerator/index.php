<?php
/* This was made to automate some code generation for GDScript.
 * The idea being you can have a script be generated with certain features removed from the source rather than ignored during runtime.
 * As GDScript is a kinda slow and interpreted language, this will hopefully improve runtime without sacrifing optional functionality.
 * WIP/TODO A lot.
//*/
//Validate script environment
$SCRIPT_VERSION_REQUIREMENTS = array(
	"Core" => array("min"=>"8"), // Version 8+ allows for mixed type unions / parameter type hinting.
);
//No type-hinting in this function is allowed for PHP Core versions older than Major Release 5.
function validate_script_requirements($requirements)
{
	//Returns true if the current PHP environment supports requirements.
	//Returns false otherwise.
	//Unlike version_compare() works before version 4.1.0(but after 4.0.0) and supports more options(but slower(?)).
	//get_loaded_extensions(), foreach, in_array(), explode(), trim(),
	// phpversion(), count(), isset(), is_numeric(), and intval()
	// were all available since at least Core Major Release 4. Hopefully good enough.
	$ext = get_loaded_extensions();
	foreach($requirements as $key => $value)
	{
		//Validate extension presence.
		if(!in_array($key, $ext))
			return false;
		$current_version = explode('.', trim(phpversion($key)));
		$current_version_count = count($current_version);
		//Pre-process/Validate current extension's version.
		for($c = 0; $c < $current_version_count; $c++)
		{
			$current_version[$c] = trim($current_version[$c]);
			if(!is_numeric($current_version[$c]))
				return false;
		}
		//Validate minimum.
		if(isset($value["min"]))
		{
			$minimum_version = explode('.', trim($value["min"]));
			$minimum_version_count = count($minimum_version);
			for($m = 0; $m < $minimum_version_count; $m++)
			{
				if($m >= $current_version_count)
					return false;
				$minimum_version[$m] = trim($minimum_version[$m]);
				if(!is_numeric($minimum_version[$m]))
					return false;
				if(intval($current_version[$m]) < intval($minimum_version[$m]))
					return false;
			}
		}
		//Validate maximum.
		if(isset($value["max"]))
		{
			$maximum_version = explode('.', trim($value["max"]));
			$maximum_version_count = count($maximum_version);
			for($m = 0; $m < $maximum_version_count; $m++)
			{
				if($m >= $current_version_count)
					return false;
				$maximum_version[$m] = trim($maximum_version[$m]);
				if(!is_numeric($maximum_version[$m]))
					return false;
				if(intval($current_version[$m]) > intval($maximum_version[$m]))
					return false;
			}
		}
	}
	return true;
}
if(!validate_script_requirements($SCRIPT_VERSION_REQUIREMENTS))
{
	// Handle unsupported environment.
	// For this case we will just die. R.I.P.
	die("Internal Server Error, contact administrator.");
}

//Debugging/Logging Function(s)
//!TODO Add option to debug/log to other file(?)
$debug_mode = false;
function printd(string $s, string $prefix="[DEBUG]: ", string $suffix="\n") : bool
{
	//Prints s only if debug_mode is enabled.
	//Returns global debug_mode value.
	global $debug_mode;
	if($debug_mode)
		print("" . $prefix . $s . $suffix);
	return $debug_mode;
}
if($debug_mode)
{
	// Show all errors
	error_reporting(E_ALL);
}

//Array Function(s)
//Splat operator "..." requires PHP Core version of 5.6 or higher.
function array_merge_shallow(array $base_array, array ...$next_arrays):array
{
	//Functions like array_merge, however child elements that are also arrays are not merged.
	//Array elements in base_array are maintained. Overlapping keys are replaced see array_merge.
	//Returns merged results(array).
	foreach($next_arrays as $next_array)
		foreach($next_array as $key => $value)
		{
			if(is_array($value))
				continue;
			$base_array[$key] = $value;
		}
	return $base_array;
}
#Code from: https://stackoverflow.com/questions/1319903/how-to-flatten-a-multidimensional-array
function array_flatten(array $array)
{
	//Requires PHP version 5.3+
    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;
}
//Modified code from source: https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
if (!function_exists('array_is_list')) {
	// This check allows script's version check to stay at 8 rather than 8.1
	function array_is_list(array $arr)
	{
		if($arr === [])
			return true;
		return array_keys($arr) === range(0, count($arr) - 1);
	}
}

//JSON Function(s)
function load_json( string $file_path )
{
	//Attempts to read json from file, decode, and then return value.
	// Read the JSON file.
	$json = file_get_contents("" . $file_path);
	if($json == false)
	{
		printd("load_json() Failed to read script file into memory.", "[ERROR]: ");
		return null;
	}
	else
	{
		printd("load_json() Finished reading in the raw JSON file.");
	}
	// Decode the JSON file
	return(json_decode($json, true, 512));
}

//Script Specific Stuff
//Handling Default(s) Function(s)
$DEFAULT_OPTIONS = array(
	"comment_defaults"       => array(
		"prefer_multiline"  => true,
		"comment_char"      => '#',
		"comments_char"     => "\"\"\"",
		"padding_char"      => " ",
		"header_padding"    => 1,
		"comment_padding"   => 1,
	),
	"constant_defaults"     => array(
		"constant_prefix"   => "DEFAULT_",
		"constant_suffix"   => "",
		"generate_constant" => false,
	),
	"end_line"              => "\n",
	"export_variable"       => false,
	"print_header_constants"=> true,
	"print_header_variables"=> true,
	"print_header_setgets"  => true, // Unused in Godot Version 4+
	"print_header_functions"=> true,
	"print_header_methods"  => true,
	"print_header_events"   => true,
	"header_constants"      => "Script Constant(s) / Default(s)",
	"header_variables"      => "Script Variable(s) / Exported Variable(s)",
	"header_setgets"        => "Script setget/set/get Function(s)", // Unused in Godot Version 4+
	"header_functions"      => "Script Function(s)",
	"header_methods"        => "Script Method(s)",
	"header_events"         => "Script Event(s)",
); // Default values can be replaced/overriden via json settings.
function get_defaults(string $group_name = ""): array
{
	//Returns default parameters for entire script for specific to one group of default values.
	global $DEFAULT_OPTIONS;
	if($group_name == "")
		//Return only global default values.
		return array_merge_shallow(array(), $DEFAULT_OPTIONS);
	if(!array_key_exists($group_name, $DEFAULT_OPTIONS))
	{
		//Try adding suffix?
		$group_name .= "_defaults";
		if(!array_key_exists($group_name, $DEFAULT_OPTIONS))
		{
			printd("get_defaults() encountered request for unknown group named: '" . $group_name . "'.");//!Debugging
			//Return global default values.
			return get_defaults();
		}
	}
	return array_merge_shallow($DEFAULT_OPTIONS[$group_name], $DEFAULT_OPTIONS);
}

//Global Variables
$target_file_path = "./Script.json";
$json = "";
$json_data = array();

//!TODO
//The meaning of these flags are/should be script/json specific?
$script_mode           = 0b0000000000000000;//uint16
$FLAG_DISABLE_ALL      = 0b1111111111111111;
$FLAG_DISABLE_DEBUG    = 0b0000000000000001;
$FLAG_DISABLE_ROTATION = 0b0000000000000010;
$FLAG_DISABLE_MOTION   = 0b0000000000000100;
$FLAG_DISABLE_COLLISION= 0b0000000000001000;
$FLAG_DISABLE_LERP     = 0b0000000000010000;
$FLAG_DISABLE_LIMITS   = 0b0000000000100000;
$FLAG_DISABLE_MOUSE    = 0b0000000001000000;

//Function(s)
function is_mode( int $test_mode ) : bool
{
	//!TODO
	//Returns true if the mode of the script matches the mode provided via parameter.
	//Used to enable/include vs disable/exclude parts of the script during generation.
	return true;
}
//Method(s)
function print_header(string $s, array $defaults = null, int $padding_amount = -1)
{
	//Prints header comment block used to mark areas in the generated script.
	//Returns void(method).
	if($defaults == null)
		//No defaults provided, using the generic defaults.
		$defaults = get_defaults("comment");
	if($padding_amount < 0)
		$padding_amount = $defaults["header_padding"];
	$s = trim($s);
	$s_len = strlen($s);
	//Future proofing in-case comment marker changes to more than one character in the future e.g. //
	$p_len = strlen($defaults["padding_char"]);
	$c_len = strlen($defaults["comment_char"]);
	$m_len = $s_len + (2 * $c_len) + (2 * $p_len);//Comment markers and padding are added to the begining and end
	$c_cnt = ceil($m_len / $c_len);
	$c_line = substr(str_repeat($defaults["comment_char"], $c_cnt), 0, $m_len);
	$m = ($defaults["comment_char"] . str_repeat($defaults["padding_char"], $padding_amount) . $s . str_repeat($defaults["padding_char"], $padding_amount) . $defaults["comment_char"]);
	print implode($defaults["end_line"], [$c_line, $m, $c_line, ""]);
}
function print_comment(string $p_comment, array $defaults = null, int $padding_amount = -1)
{
	//Prints an in-line comment using values from defaults with optional padding.
	if($defaults == null)
		//No defaults provided, using the generic defaults.
		$defaults = get_defaults("comment");
	if($padding_amount < 0)
		$padding_amount = $defaults["comment_padding"];
	$lines = explode("" . $defaults["end_line"], $p_comment);
	$lines_count = count($lines);
	if((!$defaults["prefer_multiline"]) || ($lines_count <= 1))
		//Print single-line comment(s)
		foreach($lines as $line)
		{
			print("" . $defaults["comment_char"] . str_repeat($defaults["padding_char"], $padding_amount) . $line . $defaults["end_line"]);
		}
	else
	{
		//Print a multiline comment
		print("" . $defaults["comments_char"] . $defaults["end_line"]);
		foreach($lines as $line)
		{
			print("" . str_repeat($defaults["padding_char"], $padding_amount) . $line . $defaults["end_line"]);
		}
		print("" . $defaults["comments_char"] . $defaults["end_line"]);
	}
}
function print_comments(string|array $p_comments, array $defaults = null, int $padding_amount = -1)
{
	//Prints multiple comments using values from defaults with optional padding.
	//Expected Format(s):
	//"A comment string."
	//["An example","comment that","is multiple lines."]
	//{"comment":"A comment with it's padding specified.","comment_padding":3}
	if($defaults == null)
		//No defaults provided, using the generic defaults.
		$defaults = get_defaults("comment");
	if(is_array($p_comments))
	{
		$comment_key = "comment";
		if(array_is_list($p_comments) || !array_key_exists($comment_key, $p_comments))
		{
			//Handle array(s) recursively
			foreach($p_comments as $comment)
				print_comments($comment, $defaults, $padding_amount);
		}
		else
		{
			$padding_key = "comment_padding";
			if(array_key_exists($padding_key, $p_comments))
				$padding_amount = $p_comments[$padding_key];
			print_comments($p_comments[$comment_key], $defaults, $padding_amount);
		}
	}
	else
		//Handle string(s)
		print_comment(strval($p_comments), $defaults, $padding_amount);
}
function print_variable(array $v, array $defaults = null, bool $is_constant = false)
{
	//Expected $v Format:
	//{"tooltip":[],"comments":[],"export":true,"name":"example_variable","type":"float","value":"PI","modes":0,"generate_default":true,"setget":"function_name"}
	//Validate inputs
	if($defaults == null)
		$defaults = get_defaults("constant");
	if($v == null)
	{
		//Used for manual formatting.
		print("" . $defaults["end_line"]);
		return;
	}
	$export_key = "export";
	if(!array_key_exists($export_key, $v))
		$v[$export_key] = $defaults["export_variable"];
	$name_key = "name";
	if(!array_key_exists($name_key, $v))
	{
		printd("Failed to print variable. Variable name was undefined or empty.", "[WARN]: ");
		return;
	}
	$generate_key = "generate_default";
	if(!array_key_exists($generate_key, $v))
		$v[$generate_key] = $defaults["generate_constant"];
	//Print it.
	$comments_key = "comments";
	if(array_key_exists($comments_key, $v))
		print_comments($v["comments"]);
	$type_key = "type";
	if($is_constant)
		print("const " . strtoupper($v[$name_key]));
	else
	{
		if($v[$export_key])
		{
			print("export");
			if(array_key_exists($type_key, $v))
				print("(" . $v[$type_key] . ")");
			print(" ");
		}
		print("var " . $v[$name_key]);
	}
	if(array_key_exists($type_key, $v))
		print(":" . $v[$type_key]);
	$value_key = "value";
	if(array_key_exists($value_key, $v))
	{
		print(" = ");
		if(!$v[$generate_key])
			print("" . $v[$value_key]);
		else
			print("" . $defaults["constant_prefix"] . strtoupper($v[$name_key]) . $defaults["constant_suffix"]);
	}
	$setget_key = "setget";
	if((!$is_constant) && (array_key_exists($setget_key, $v)))
		print(" setget " . $v[$setget_key]);
	print("" . $defaults["end_line"]);
}
function preprocess_variable_constants(array $json_data, array $defaults = null)
{
	//Pushes any generated constant values from json_data["variables"] array to json_data["constants"] array,
	// unless constant value already exists in said array.
	//Unsets generate_key values of variables after completed.
	//Assumes json_data is loaded and valid.
	//Returns updated $json_data(array).
	if($defaults == null)
	{
		//No defaults provided, using the generic defaults.
		$defaults = get_defaults("constant");
	}
	//Validate json_data arrays.
	$constants_key = "constants";
	if(!array_key_exists($constants_key, $json_data))
	{
		printd("preprocess_variable_constants() failed to locate any constants in json_data. This maybe intended.", "[WARN]: ");//!Debugging
		$json_data[$constants_key] = array();
	}
	$variables_key = "variables";
	if(!array_key_exists($variables_key, $json_data))
	{
		printd("preprocess_variable_constants() failed to locate any variables in json_data. This maybe intended.", "[WARN]: ");//!Debugging
		$json_data[$variables_key] = array();
		return($json_data);
	}
	//Both arrays exist in json_data at this point.
	$name_key = "name";
	$type_key = "type";
	$value_key = "value";
	$generate_key = "generate_default";
	foreach($json_data[$variables_key] as $key => $value)
	{
		if(!array_key_exists($name_key, $value))
			continue;// Variable entry is probably being used for manual formatting.
		if(array_key_exists($generate_key, $value))
		{
			if(!$value[$generate_key])
				continue;
			$new_constants_name = ("" . $defaults["constant_prefix"] . $value[$name_key] . $defaults["constant_suffix"]);
			if(in_array($new_constants_name, $json_data[$constants_key]))
				//Already has a constant defined with the same name.
				//Continues to prevent overwriting/name conflicts.
				continue;
			array_push($json_data[$constants_key],
			array(
				$name_key => $new_constants_name,
				$type_key => $value[$type_key],
				$value_key => $value[$value_key]
			));
			unset($json_data[$variables_key][$generate_key]);
		}
	}
	return($json_data);
}
function preprocess_tooltips(array $json_data, array $defaults = null)
{
	//Based on export flag of variables in json_data,
	//converts any tool-tip values to appropriate associative or list type comments.
	//Unsets all variable entries tooltip values.
	//Returns updated $json_data(array).
	if($defaults == null)
		$defaults = get_defaults("comment");
	$variables_key = "variables";
	if(!array_key_exists($variables_key, $json_data))
	{
		printd("preprocess_tooltips() failed to locate any variables in json_data. This maybe intended.", "[WARN]: ");//!Debugging
		$json_data[$variables_key] = array();
	}
	$padding_key = "comment_padding";
	$export_key = "export";
	$name_key = "name";
	$comments_key = "comments";
	$tooltip_key = "tooltip";
	foreach($json_data[$variables_key] as $i => $variable)
	{
		if(!array_key_exists($name_key, $variable))
			// Probably being used for manual formatting.
			continue;
		if(!array_key_exists($comments_key, $variable))
		{
			printd("preprocess_tooltips() failed to locate any comments in json_data for variable: '" . $variable[$name_key] . "'. This maybe intended.", "[WARN]: ");//!Debugging
			$json_data[$variables_key][$i][$comments_key] = array();
		}
		if(!array_key_exists($tooltip_key, $variable))
			continue;
		$mark = $defaults["comment_char"];
		$padding_str = str_repeat($defaults["padding_char"], $defaults[$padding_key]);
		if($defaults["export_variable"])
		{
			if(array_key_exists($export_key, $variable))
				if(!$variable[$export_key])
					$mark = "";
		}
		elseif(!array_key_exists($export_key, $variable))
			//Default to non-export normal comment.
			$mark = "";
		elseif(!$variable[$export_key])
			//(unnecessarily)Explicit non-export normal comment.
			$mark = "";
		//tool-tip array should already be a flat list-type array. But just to be safe...
		foreach(array_flatten($variable[$tooltip_key]) as $tooltip)
			array_push($json_data[$variables_key][$i][$comments_key],
			array(
				"comment" => ("" . $mark . $padding_str . $tooltip),
				$padding_key => 0,
			));
		unset($json_data[$variables_key][$i][$tooltip_key]);
	}
	return $json_data;
}
function print_function(array $f)
{
	//Prints a GDScript function from JSON associative array
	$temp_flag = false;
	$temp_count = 0;
	if($f == null)
	{
		//Used for manual formatting.
		print("\n");
		return;
	}
	if($f["name"] == null or strlen($f["name"]) <= 0)//Warning: strlen(null) is deprecated.
	{
		printd("Failed to print function. Function name was undefined or empty.", "[WARN]: ");
		return;
	}
	print("func " . strtolower($f["name"]) . "(");
	if(isset($f["parameters"]))
	{
		$temp_count = count($f["parameters"]);
		if($temp_count > 0)
			$temp_flag = true;
	}
	if($temp_flag)
	{
		foreach($f["parameters"] as $i => $next)
		{
			print($next);
			if(($i + 1) < $temp_count)
				print(", ");
		}
	}
	$temp_flag = false;
	$temp_count = 0;
	print(")");
	if(isset($f["type"]))
	{
		print(" -> " . $f["type"]);
	}
	print(":\n");
	if(isset($f["code"]))
	{
		if(count($f["code"]) > 0)
			$temp_flag = true;
	}
	if($temp_flag)
	{
		foreach($f["code"] as $i => $next)
			print("\t" . $next . "\n");
	} else {
		print("\tpass\n");
	}
	$temp_flag = false;
}
function print_script( array $json_data, int $script_mode = 0 )
{
	//Processes and prints a GDScript from JSON data stored in associative array $json_data.
	//Pre-Processing
	$json_data = preprocess_tooltips(preprocess_variable_constants($json_data));
	//Print Header Comments
	$header_comments_key = "header_comments";
	if(array_key_exists($header_comments_key, $json_data))
	{
		print_comments($json_data[$header_comments_key]);
		print("\n");
	}
	//Print Constants
	$constants_key = "constants";
	if(array_key_exists($constants_key, $json_data))
	{
		print_header("Constants / Defaults");
		foreach($json_data[$constants_key] as $next)
			print_variable($next, null, true);
		print("\n");
	}
	//Print Variables
	$variables_key = "variables";
	if(array_key_exists($variables_key, $json_data))
	{
		print_header("Variables / Exported Variables");
		foreach($json_data[$variables_key] as $i => $next)
			print_variable($next, null, false);
		print("\n");
	}
	//Functions/Methods
	$functions_key = "functions";
	if(array_key_exists($functions_key, $json_data))
	{
		print_header("Functions / Methods / Events / Signals / SetGets");
		foreach($json_data[$functions_key] as $i => $next)
		{
			print_function($next);
		}
		print("\n");
	}
}

// Start main execution.
printd("Script's main execution has started.");//!Debugging
$json_data = load_json($target_file_path);
if($json_data == null)
{
	printd("Failed to parse JSON data.");//!Debugging
	die();
}
// Display data(Debugging)
if($debug_mode)
{
	printd("Parsed JSON recursive value: ");
	print_r($json_data);
}
// Print
print_script($json_data);
//Finish
printd("Script has stopped.");//!Debugging
?>